<?php

declare(strict_types=1);

namespace App\Application\Member\Commands;

use App\Application\Member\DTOs\MemberDto;
use App\Domain\Member\MemberId;
use App\Domain\Member\MemberRepositoryInterface;
use App\Domain\Member\MemberSkillId;
use App\Domain\Member\SkillProficiency;
use App\Domain\Skill\SkillId;
use App\Infrastructure\Events\DomainEventDispatcher;
use Illuminate\Support\Str;
use RuntimeException;

final class UpsertMemberSkillHandler
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(UpsertMemberSkillCommand $command): MemberDto
    {
        $member = $this->memberRepository->findById(new MemberId($command->memberId));
        if ($member === null) {
            throw new RuntimeException('Member not found: '.$command->memberId);
        }

        $skillId = new SkillId($command->skillId);
        $memberSkillId = new MemberSkillId((string) Str::uuid7());
        $member->addOrUpdateSkill(
            $memberSkillId,
            $skillId,
            new SkillProficiency($command->proficiency),
        );

        $this->memberRepository->save($member);

        $this->eventDispatcher->dispatchAll($member->pullDomainEvents());

        return MemberDto::fromDomain($member);
    }
}
