<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\AllocationModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberSkillModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\RequiredSkillModel;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use App\Models\AuditLog;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 主要リソースを CSV でストリーム出力するコントローラ。
 * Content-Disposition: attachment; filename=... でブラウザのダウンロードに。
 */
class ExportController extends Controller
{
    public function members(): StreamedResponse
    {
        return $this->stream('members.csv', function ($out): void {
            $skills = SkillModel::query()->get()->keyBy('id');
            fputcsv($out, ['member_id', 'name', 'standard_working_hours', 'skill', 'proficiency']);

            MemberModel::query()->with('skills')->orderBy('name')->chunk(200, function ($chunk) use ($out, $skills): void {
                foreach ($chunk as $m) {
                    if ($m->skills->isEmpty()) {
                        fputcsv($out, [$m->id, $m->name, $m->standard_working_hours, '', '']);
                        continue;
                    }
                    foreach ($m->skills as $ms) {
                        $skill = $skills->get($ms->skill_id);
                        fputcsv($out, [
                            $m->id,
                            $m->name,
                            $m->standard_working_hours,
                            $skill?->name ?? $ms->skill_id,
                            $ms->proficiency,
                        ]);
                    }
                }
            });
        });
    }

    public function projects(): StreamedResponse
    {
        return $this->stream('projects.csv', function ($out): void {
            $skills = SkillModel::query()->get()->keyBy('id');
            fputcsv($out, ['project_id', 'name', 'required_skill', 'required_proficiency', 'headcount']);

            ProjectModel::query()->with('requiredSkills')->orderBy('name')->chunk(200, function ($chunk) use ($out, $skills): void {
                foreach ($chunk as $p) {
                    if ($p->requiredSkills->isEmpty()) {
                        fputcsv($out, [$p->id, $p->name, '', '', '']);
                        continue;
                    }
                    foreach ($p->requiredSkills as $rs) {
                        $skill = $skills->get($rs->skill_id);
                        fputcsv($out, [
                            $p->id,
                            $p->name,
                            $skill?->name ?? $rs->skill_id,
                            $rs->required_proficiency,
                            $rs->headcount,
                        ]);
                    }
                }
            });
        });
    }

    public function allocations(): StreamedResponse
    {
        return $this->stream('allocations.csv', function ($out): void {
            $members = MemberModel::query()->get()->keyBy('id');
            $projects = ProjectModel::query()->get()->keyBy('id');
            $skills = SkillModel::query()->get()->keyBy('id');
            fputcsv($out, [
                'allocation_id', 'status',
                'member', 'project', 'skill',
                'allocation_percentage', 'period_start', 'period_end',
            ]);

            AllocationModel::query()->orderBy('period_start')->chunk(500, function ($chunk) use ($out, $members, $projects, $skills): void {
                foreach ($chunk as $a) {
                    fputcsv($out, [
                        $a->id,
                        $a->status,
                        $members->get($a->member_id)?->name ?? $a->member_id,
                        $projects->get($a->project_id)?->name ?? $a->project_id,
                        $skills->get($a->skill_id)?->name ?? $a->skill_id,
                        $a->allocation_percentage,
                        $a->period_start?->format('Y-m-d'),
                        $a->period_end?->format('Y-m-d'),
                    ]);
                }
            });
        });
    }

    public function auditLogs(): StreamedResponse
    {
        return $this->stream('audit-logs.csv', function ($out): void {
            fputcsv($out, [
                'id', 'created_at', 'user_email',
                'event_type', 'aggregate_type', 'aggregate_id', 'payload',
            ]);

            AuditLog::query()->with('user:id,email')->orderByDesc('created_at')->chunk(500, function ($chunk) use ($out): void {
                foreach ($chunk as $log) {
                    fputcsv($out, [
                        $log->id,
                        $log->created_at?->toIso8601String(),
                        $log->user?->email ?? '',
                        $log->event_type,
                        $log->aggregate_type,
                        $log->aggregate_id,
                        json_encode($log->payload, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
        });
    }

    private function stream(string $filename, \Closure $writer): StreamedResponse
    {
        return new StreamedResponse(function () use ($writer): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            // Excel で開きやすいように UTF-8 BOM を先頭に書く
            fwrite($out, "\xEF\xBB\xBF");
            $writer($out);
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Cache-Control' => 'no-store',
        ]);
    }
}
