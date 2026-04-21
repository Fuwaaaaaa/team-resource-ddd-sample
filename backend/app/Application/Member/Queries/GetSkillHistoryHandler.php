<?php

declare(strict_types=1);

namespace App\Application\Member\Queries;

use App\Application\Member\DTOs\SkillHistoryEntryDto;
use App\Models\AuditLog;

/**
 * audit_logs を直接読むリードモデル。
 *
 * MemberSkillUpdated は `payload = { skillId, proficiency }` で記録されている。
 * 新規に Projection テーブルを作らず、既存の audit_logs を時系列クエリで引く。
 * データ量が増えたら専用テーブルへの移行を検討するが、現状はこれで十分。
 */
final class GetSkillHistoryHandler
{
    /** @return SkillHistoryEntryDto[] */
    public function handle(GetSkillHistoryQuery $query): array
    {
        $builder = AuditLog::query()
            ->with('user:id,name')
            ->where('aggregate_type', 'member')
            ->where('aggregate_id', $query->memberId)
            ->where('event_type', 'MemberSkillUpdated')
            ->orderBy('created_at', 'asc');

        if ($query->periodStart !== null) {
            $builder->where('created_at', '>=', $query->periodStart);
        }
        if ($query->periodEnd !== null) {
            $builder->where('created_at', '<=', $query->periodEnd);
        }

        $entries = [];
        foreach ($builder->get() as $log) {
            $payload = $log->payload ?? [];
            $skillId = (string) ($payload['skillId'] ?? '');
            $proficiency = (int) ($payload['proficiency'] ?? 0);

            if ($query->skillId !== null && $skillId !== $query->skillId) {
                continue;
            }

            $entries[] = new SkillHistoryEntryDto(
                skillId: $skillId,
                proficiency: $proficiency,
                changedAt: $log->created_at->toIso8601String(),
                changedBy: $log->user_id !== null ? (string) $log->user_id : null,
                changedByName: $log->user?->name,
            );
        }

        return $entries;
    }
}
