<?php

declare(strict_types=1);

namespace App\Reports;

use App\Application\Project\DTOs\ProjectKpiDto;
use App\Application\Project\Queries\GetProjectKpiHandler;
use App\Application\Project\Queries\GetProjectKpiQuery;
use App\Domain\Project\ProjectId;
use App\Domain\Project\ProjectRepositoryInterface;
use App\Domain\Skill\SkillRepositoryInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

/**
 * Project Status Report を PDF バイナリとしてレンダリングする。
 *
 * DomPDF は isRemoteEnabled=false で外部リソース読み込みを禁止 (XXE / SSRF 防止)。
 * Blade を使わずインライン HTML を生成する (NotificationMail と同じパターン)。
 */
final class ProjectStatusReportRenderer
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private SkillRepositoryInterface $skillRepository,
        private GetProjectKpiHandler $kpiHandler,
    ) {}

    public function render(string $projectId, ?string $referenceDate = null): string
    {
        $project = $this->projectRepository->findById(new ProjectId($projectId));
        if ($project === null) {
            throw new RuntimeException("Project not found: {$projectId}");
        }

        $kpi = $this->kpiHandler->handle(new GetProjectKpiQuery(
            projectId: $projectId,
            referenceDate: $referenceDate ?? date('Y-m-d'),
        ));

        // スキル名解決マップ (Report HTML の行で UUID ではなく人間可読名を出す)
        $skillNames = [];
        foreach ($this->skillRepository->findAll() as $skill) {
            $skillNames[$skill->id()->toString()] = $skill->name()->toString();
        }

        $html = $this->buildHtml($kpi, $skillNames, $project->plannedStartDate(), $project->plannedEndDate());

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans'); // multibyte (日本語) 対応

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /** @param array<string, string> $skillNames */
    private function buildHtml(
        ProjectKpiDto $kpi,
        array $skillNames,
        ?\DateTimeImmutable $plannedStart,
        ?\DateTimeImmutable $plannedEnd,
    ): string {
        $name = $this->e($kpi->projectName);
        $status = $this->e($kpi->status);
        $refDate = $this->e($kpi->referenceDate);
        $fulfillment = number_format($kpi->fulfillmentRate, 1);
        $personMonths = number_format($kpi->personMonthsAllocated, 2);
        $period = $plannedStart && $plannedEnd
            ? $plannedStart->format('Y-m-d').' 〜 '.$plannedEnd->format('Y-m-d')
            : '未設定';
        $period = $this->e($period);
        $generatedAt = date('Y-m-d H:i:s');

        $skillRows = '';
        foreach ($kpi->requiredSkillsBreakdown as $entry) {
            $skillName = $this->e($entry['skillName'] ?? $skillNames[$entry['skillId']] ?? $entry['skillId']);
            $gap = (int) $entry['gap'];
            $gapClass = $gap < 0 ? 'critical' : ($gap === 0 ? 'neutral' : 'ok');
            $skillRows .= sprintf(
                '<tr><td>%s</td><td class="num">%d</td><td class="num">%d</td><td class="num %s">%+d</td></tr>',
                $skillName,
                (int) $entry['requiredHeadcount'],
                (int) $entry['qualifiedHeadcount'],
                $gapClass,
                $gap,
            );
        }
        if ($skillRows === '') {
            $skillRows = '<tr><td colspan="4" class="muted">(required skills 未設定)</td></tr>';
        }

        $upcomingRows = '';
        foreach ($kpi->upcomingEnds as $u) {
            $upcomingRows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td class="num">%d 日</td></tr>',
                $this->e((string) $u['memberName']),
                $this->e((string) $u['endDate']),
                (int) $u['daysRemaining'],
            );
        }
        if ($upcomingRows === '') {
            $upcomingRows = '<tr><td colspan="3" class="muted">(30 日以内に終了するアサインなし)</td></tr>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>Project Status — {$name}</title>
<style>
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; color: #222; margin: 24pt; }
  h1 { font-size: 18pt; margin: 0 0 4pt 0; }
  .subtle { color: #666; font-size: 9pt; }
  .meta { margin: 12pt 0 18pt 0; }
  .meta dt { float: left; width: 120pt; font-weight: bold; color: #444; }
  .meta dd { margin: 0 0 4pt 120pt; }
  h2 { font-size: 13pt; border-bottom: 1px solid #ddd; padding-bottom: 2pt; margin-top: 20pt; }
  table { width: 100%; border-collapse: collapse; margin-top: 6pt; }
  th, td { border: 1px solid #ccc; padding: 4pt 6pt; text-align: left; font-size: 10pt; }
  th { background: #f5f5f5; }
  .num { text-align: right; font-variant-numeric: tabular-nums; }
  .critical { color: #c00; font-weight: bold; }
  .ok { color: #060; }
  .neutral { color: #666; }
  .muted { color: #999; text-align: center; font-style: italic; }
  .kpi-cards { margin: 8pt 0 16pt 0; }
  .kpi-cards .card { display: inline-block; border: 1px solid #ddd; padding: 6pt 10pt; margin-right: 6pt; min-width: 100pt; }
  .kpi-cards .card .label { font-size: 9pt; color: #666; }
  .kpi-cards .card .value { font-size: 14pt; font-weight: bold; margin-top: 2pt; }
  .footer { margin-top: 24pt; font-size: 8pt; color: #999; border-top: 1px solid #eee; padding-top: 6pt; }
</style>
</head>
<body>
  <h1>Project Status Report</h1>
  <div class="subtle">{$name} &middot; as of {$refDate}</div>

  <dl class="meta">
    <dt>Status</dt><dd>{$status}</dd>
    <dt>Planned period</dt><dd>{$period}</dd>
  </dl>

  <div class="kpi-cards">
    <div class="card">
      <div class="label">Fulfillment rate</div>
      <div class="value">{$fulfillment}%</div>
    </div>
    <div class="card">
      <div class="label">Required headcount</div>
      <div class="value">{$kpi->totalRequiredHeadcount}</div>
    </div>
    <div class="card">
      <div class="label">Qualified headcount</div>
      <div class="value">{$kpi->totalQualifiedHeadcount}</div>
    </div>
    <div class="card">
      <div class="label">Active allocations</div>
      <div class="value">{$kpi->activeAllocationCount}</div>
    </div>
    <div class="card">
      <div class="label">Allocated person-months</div>
      <div class="value">{$personMonths}</div>
    </div>
  </div>

  <h2>Required skills breakdown</h2>
  <table>
    <thead>
      <tr><th>Skill</th><th class="num">Required</th><th class="num">Qualified</th><th class="num">Gap</th></tr>
    </thead>
    <tbody>{$skillRows}</tbody>
  </table>

  <h2>Upcoming assignment ends (next 30 days)</h2>
  <table>
    <thead>
      <tr><th>Member</th><th>End date</th><th class="num">Remaining</th></tr>
    </thead>
    <tbody>{$upcomingRows}</tbody>
  </table>

  <div class="footer">
    Generated at {$generatedAt} by Team Resource Dashboard
  </div>
</body>
</html>
HTML;
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
