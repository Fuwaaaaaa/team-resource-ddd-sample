<?php

declare(strict_types=1);

namespace App\Application\Member\Commands;

use App\Application\Member\DTOs\MemberDto;
use App\Domain\Member\MemberId;
use App\Domain\Member\MemberName;
use App\Domain\Member\MemberRepositoryInterface;
use App\Domain\Member\StandardWorkingHours;
use ReflectionClass;
use RuntimeException;

final class UpdateMemberHandler
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
    ) {}

    public function handle(UpdateMemberCommand $command): MemberDto
    {
        $member = $this->memberRepository->findById(new MemberId($command->memberId));
        if ($member === null) {
            throw new RuntimeException('Member not found: '.$command->memberId);
        }

        if ($command->name !== null) {
            $this->setProperty($member, 'name', new MemberName($command->name));
        }
        if ($command->standardWorkingHours !== null) {
            $member->updateStandardWorkingHours(new StandardWorkingHours($command->standardWorkingHours));
        }

        $this->memberRepository->save($member);

        return MemberDto::fromDomain($member);
    }

    private function setProperty(object $object, string $property, mixed $value): void
    {
        $ref = new ReflectionClass($object);
        $prop = $ref->getProperty($property);
        $prop->setValue($object, $value);
    }
}
