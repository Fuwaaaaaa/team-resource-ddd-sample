<?php

declare(strict_types=1);

namespace App\Application\Member\Commands;

use App\Application\Member\DTOs\MemberDto;
use App\Domain\Member\Events\MemberCreated;
use App\Domain\Member\Member;
use App\Domain\Member\MemberName;
use App\Domain\Member\MemberRepositoryInterface;
use App\Domain\Member\StandardWorkingHours;
use App\Infrastructure\Events\DomainEventDispatcher;

final class CreateMemberHandler
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(CreateMemberCommand $command): MemberDto
    {
        $id = $this->memberRepository->nextIdentity();
        $member = new Member(
            $id,
            new MemberName($command->name),
            new StandardWorkingHours($command->standardWorkingHours),
        );
        $this->memberRepository->save($member);

        // Member コンストラクタは MemberCreated を発火しないため、Application 層で明示的に発行する。
        // (Domain を無改変に保つため)
        $this->eventDispatcher->dispatchAll([
            new MemberCreated($id),
            ...$member->pullDomainEvents(),
        ]);

        return MemberDto::fromDomain($member);
    }
}
