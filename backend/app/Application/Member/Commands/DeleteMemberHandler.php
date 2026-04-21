<?php

declare(strict_types=1);

namespace App\Application\Member\Commands;

use App\Domain\Member\MemberId;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentMemberRepository;

final class DeleteMemberHandler
{
    public function __construct(
        private EloquentMemberRepository $memberRepository,
    ) {}

    public function handle(string $memberId): void
    {
        $this->memberRepository->delete(new MemberId($memberId));
    }
}
