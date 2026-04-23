<?php

declare(strict_types=1);

namespace App\Application\AllocationChangeRequest\Commands;

use App\Application\Allocation\Commands\CreateAllocationCommand;
use App\Application\Allocation\Commands\CreateAllocationHandler;
use App\Application\Allocation\Commands\RevokeAllocationHandler;
use App\Application\Allocation\DTOs\AllocationDto;
use App\Application\AllocationChangeRequest\DTOs\AllocationChangeRequestDto;
use App\Domain\AllocationChangeRequest\AllocationChangeRequestId;
use App\Domain\AllocationChangeRequest\AllocationChangeRequestRepositoryInterface;
use App\Domain\AllocationChangeRequest\ChangeRequestType;
use App\Infrastructure\Events\DomainEventDispatcher;
use DateTimeImmutable;
use RuntimeException;

/**
 * 承認時に対応する副作用 (allocation 作成 / 取消) を実行する。
 * 失敗した場合は request は未変更のまま例外を伝播する。
 */
final class ApproveAllocationChangeRequestHandler
{
    public function __construct(
        private AllocationChangeRequestRepositoryInterface $repository,
        private CreateAllocationHandler $createAllocationHandler,
        private RevokeAllocationHandler $revokeAllocationHandler,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(string $requestId, int $decidedBy, ?string $decisionNote = null): AllocationChangeRequestDto
    {
        $request = $this->repository->findById(new AllocationChangeRequestId($requestId));
        if ($request === null) {
            throw new RuntimeException('AllocationChangeRequest not found: '.$requestId);
        }

        $payload = $request->payload();

        $resultingAllocationId = match ($request->type()) {
            ChangeRequestType::CreateAllocation => $this->applyCreate($payload->toArray())->id,
            ChangeRequestType::RevokeAllocation => $this->applyRevoke((string) $payload->get('allocationId'))->id,
        };

        $request->approve(
            decidedBy: $decidedBy,
            decidedAt: new DateTimeImmutable,
            note: $decisionNote,
            resultingAllocationId: $resultingAllocationId,
        );

        $this->repository->save($request);
        $this->eventDispatcher->dispatchAll($request->pullDomainEvents());

        return AllocationChangeRequestDto::fromDomain($request);
    }

    /** @param array<string, mixed> $payload */
    private function applyCreate(array $payload): AllocationDto
    {
        $cmd = new CreateAllocationCommand(
            memberId: (string) $payload['memberId'],
            projectId: (string) $payload['projectId'],
            skillId: (string) $payload['skillId'],
            allocationPercentage: (int) $payload['allocationPercentage'],
            periodStart: (string) $payload['periodStart'],
            periodEnd: (string) $payload['periodEnd'],
            dryRun: false,
        );
        $result = $this->createAllocationHandler->handle($cmd);
        if (! $result instanceof AllocationDto) {
            throw new RuntimeException('Unexpected result type from CreateAllocationHandler');
        }

        return $result;
    }

    private function applyRevoke(string $allocationId): AllocationDto
    {
        return $this->revokeAllocationHandler->handle($allocationId);
    }
}
