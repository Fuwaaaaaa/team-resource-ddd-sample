<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Application\Import\DTOs\ImportReportDto;
use App\Application\Member\Commands\CreateMemberCommand;
use App\Application\Member\Commands\CreateMemberHandler;
use Throwable;

/**
 * members.csv のバルクインポート。必須列: name。
 * 任意列: standard_working_hours (既定 8.0)。
 *
 * 各行は既存の CreateMemberHandler を経由するため、ドメインイベントは
 * 通常通り発火し、監査ログ / 通知にも反映される。
 */
final class ImportMembersHandler
{
    public function __construct(
        private CreateMemberHandler $createMember,
    ) {}

    public function handle(string $csv): ImportReportDto
    {
        $imported = 0;
        $failures = [];

        foreach (CsvReader::rows($csv) as $lineNo => $row) {
            try {
                $name = (string) ($row['name'] ?? '');
                if ($name === '') {
                    throw new \InvalidArgumentException('name is required');
                }
                $hours = (float) ($row['standard_working_hours'] ?? 8.0);
                if ($hours <= 0.0 || $hours > 24.0) {
                    throw new \InvalidArgumentException('standard_working_hours must be between 0 and 24');
                }

                $this->createMember->handle(new CreateMemberCommand(
                    name: $name,
                    standardWorkingHours: $hours,
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
