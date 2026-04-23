<?php

declare(strict_types=1);

namespace App\Domain\AllocationChangeRequest;

use App\Domain\AllocationChangeRequest\Events\AllocationChangeRequestApproved;
use App\Domain\AllocationChangeRequest\Events\AllocationChangeRequestRejected;
use App\Domain\AllocationChangeRequest\Events\AllocationChangeRequestSubmitted;
use DateTimeImmutable;
use DomainException;

/**
 * Allocation 変更申請の集約。
 *
 * ライフサイクル: pending → approved | rejected (終端)
 * 終端状態からの再遷移は不可 (DomainException)。
 */
final class AllocationChangeRequest
{
    /** @var array<int, object> */
    private array $domainEvents = [];

    private AllocationChangeRequestId $id;

    private ChangeRequestType $type;

    private ChangeRequestPayload $payload;

    private int $requestedBy;

    private ?string $reason;

    private ChangeRequestStatus $status;

    private DateTimeImmutable $requestedAt;

    private ?int $decidedBy = null;

    private ?DateTimeImmutable $decidedAt = null;

    private ?string $decisionNote = null;

    private ?string $resultingAllocationId = null;

    public function __construct(
        AllocationChangeRequestId $id,
        ChangeRequestType $type,
        ChangeRequestPayload $payload,
        int $requestedBy,
        ?string $reason,
        DateTimeImmutable $requestedAt,
    ) {
        $this->id = $id;
        $this->type = $type;
        $this->payload = $payload;
        $this->requestedBy = $requestedBy;
        $this->reason = $reason;
        $this->status = ChangeRequestStatus::Pending;
        $this->requestedAt = $requestedAt;

        $this->domainEvents[] = new AllocationChangeRequestSubmitted($id, $type, $requestedBy);
    }

    public function approve(int $decidedBy, DateTimeImmutable $decidedAt, ?string $note, ?string $resultingAllocationId): void
    {
        if ($this->status->isDecided()) {
            throw new DomainException("Change request {$this->id->toString()} already decided ({$this->status->value})");
        }

        $this->status = ChangeRequestStatus::Approved;
        $this->decidedBy = $decidedBy;
        $this->decidedAt = $decidedAt;
        $this->decisionNote = $note;
        $this->resultingAllocationId = $resultingAllocationId;

        $this->domainEvents[] = new AllocationChangeRequestApproved($this->id, $decidedBy, $resultingAllocationId);
    }

    public function reject(int $decidedBy, DateTimeImmutable $decidedAt, ?string $note): void
    {
        if ($this->status->isDecided()) {
            throw new DomainException("Change request {$this->id->toString()} already decided ({$this->status->value})");
        }

        $this->status = ChangeRequestStatus::Rejected;
        $this->decidedBy = $decidedBy;
        $this->decidedAt = $decidedAt;
        $this->decisionNote = $note;

        $this->domainEvents[] = new AllocationChangeRequestRejected($this->id, $decidedBy, $note);
    }

    public function id(): AllocationChangeRequestId
    {
        return $this->id;
    }

    public function type(): ChangeRequestType
    {
        return $this->type;
    }

    public function payload(): ChangeRequestPayload
    {
        return $this->payload;
    }

    public function requestedBy(): int
    {
        return $this->requestedBy;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function status(): ChangeRequestStatus
    {
        return $this->status;
    }

    public function requestedAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function decidedBy(): ?int
    {
        return $this->decidedBy;
    }

    public function decidedAt(): ?DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function decisionNote(): ?string
    {
        return $this->decisionNote;
    }

    public function resultingAllocationId(): ?string
    {
        return $this->resultingAllocationId;
    }

    /** @return array<int, object> */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
