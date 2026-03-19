<?php

declare(strict_types=1);

namespace App\Application\Dashboard\DTOs;

final class SkillGapWarningListDto
{
    /**
     * @param SkillGapWarningDto[] $warnings
     */
    public function __construct(
        public readonly array $warnings,
        public readonly int $totalWarnings,
        public readonly string $referenceDate,
    ) {
    }
}
