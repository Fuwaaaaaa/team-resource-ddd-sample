<?php

declare(strict_types=1);

namespace App\Application\Allocation\DTOs;

/**
 * 候補メンバーが直近 90 日に同じスキルでアサインされた案件の要約。
 * 候補表示画面で「過去にこのスキルでこういう関わり方をしていた」というコンテキストを出すのに使う。
 */
final class RecentAssignmentDto
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $projectName,
        public readonly int $allocationPercentage,
        public readonly string $periodStart,
        public readonly string $periodEnd,
        public readonly string $status, // 'active' | 'revoked'
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'projectId' => $this->projectId,
            'projectName' => $this->projectName,
            'allocationPercentage' => $this->allocationPercentage,
            'periodStart' => $this->periodStart,
            'periodEnd' => $this->periodEnd,
            'status' => $this->status,
        ];
    }
}
