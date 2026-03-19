<?php

declare(strict_types=1);

namespace App\Domain\Member;

interface MemberRepositoryInterface
{
    public function findById(MemberId $id): ?Member;

    /** @return Member[] */
    public function findAll(): array;

    /** @return Member[] */
    public function findByIds(array $ids): array;

    public function save(Member $member): void;

    public function nextIdentity(): MemberId;
}
