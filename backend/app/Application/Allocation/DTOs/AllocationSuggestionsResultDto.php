<?php

declare(strict_types=1);

namespace App\Application\Allocation\DTOs;

/**
 * `/api/allocations/suggestions` のレスポンス全体。
 * 0 件のとき hint で原因を伝え、UI が条件を緩めるよう促す。
 *
 * hint の値:
 *   - 'min_proficiency_too_high': 該当スキル保有メンバーがいるが、minimumProficiency 未満
 *   - 'all_members_at_capacity':  熟練度は満たすが、periodStart 時点で全員 100% 稼働
 *   - 'no_members_with_skill':    該当スキルを持つメンバーが 1 人もいない
 *   - null:                       1 件以上の候補があるか、上記以外の理由
 */
final class AllocationSuggestionsResultDto
{
    /** @param AllocationCandidateDto[] $candidates */
    public function __construct(
        public readonly array $candidates,
        public readonly ?string $hint,
    ) {}
}
