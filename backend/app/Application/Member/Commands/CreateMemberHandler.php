<?php

declare(strict_types=1);

namespace App\Application\Member\Commands;

use App\Application\Member\DTOs\MemberDto;
use App\Domain\Member\Member;
use App\Domain\Member\MemberName;
use App\Domain\Member\MemberRepositoryInterface;
use App\Domain\Member\StandardWorkingHours;

final class CreateMemberHandler
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
    ) {
    }

    public function handle(CreateMemberCommand $command): MemberDto
    {
        $id = $this->memberRepository->nextIdentity();
        $member = new Member(
            $id,
            new MemberName($command->name),
            new StandardWorkingHours($command->standardWorkingHours),
        );
        $this->memberRepository->save($member);

        return MemberDto::fromDomain($member);
    }
}
