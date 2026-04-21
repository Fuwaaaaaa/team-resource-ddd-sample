<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Application\Import\DTOs\ImportReportDto;
use App\Application\Project\Commands\CreateProjectCommand;
use App\Application\Project\Commands\CreateProjectHandler;
use Throwable;

/**
 * projects.csv のバルクインポート。必須列: name。
 * status は CreateProjectHandler のデフォルト (active) が適用される。
 */
final class ImportProjectsHandler
{
    public function __construct(
        private CreateProjectHandler $createProject,
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

                $this->createProject->handle(new CreateProjectCommand(name: $name));
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
