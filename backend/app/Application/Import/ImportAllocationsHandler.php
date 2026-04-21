<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Application\Allocation\Commands\CreateAllocationCommand;
use App\Application\Allocation\Commands\CreateAllocationHandler;
use App\Application\Import\DTOs\ImportReportDto;
use Throwable;

/**
 * allocations.csv のバルクインポート。必須列:
 * member_id, project_id, skill_id, percentage, start_date, end_date。
 *
 * 既存の CreateAllocationHandler を経由するため、容量超過 / 完了プロジェクト
 * への割当はドメインルールにより自動的に失敗行となる。
 */
final class ImportAllocationsHandler
{
    public function __construct(
        private CreateAllocationHandler $createAllocation,
    ) {}

    public function handle(string $csv): ImportReportDto
    {
        $imported = 0;
        $failures = [];

        foreach (CsvReader::rows($csv) as $lineNo => $row) {
            try {
                foreach (['member_id', 'project_id', 'skill_id', 'percentage', 'start_date', 'end_date'] as $required) {
                    if (! isset($row[$required]) || $row[$required] === '') {
                        throw new \InvalidArgumentException("{$required} is required");
                    }
                }

                $percentage = (int) $row['percentage'];
                if ($percentage < 1 || $percentage > 100) {
                    throw new \InvalidArgumentException('percentage must be 1..100');
                }

                $this->createAllocation->handle(new CreateAllocationCommand(
                    memberId: (string) $row['member_id'],
                    projectId: (string) $row['project_id'],
                    skillId: (string) $row['skill_id'],
                    allocationPercentage: $percentage,
                    periodStart: (string) $row['start_date'],
                    periodEnd: (string) $row['end_date'],
                    dryRun: false,
                ));
                $imported++;
            } catch (Throwable $e) {
                $failures[] = [
                    'line' => $lineNo,
                    'error' => $e->getMessage(),
                    'raw' => $row,
                ];
            }
        }

        return new ImportReportDto(imported: $imported, failures: $failures);
    }
}
