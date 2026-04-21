<?php

declare(strict_types=1);

namespace App\Application\Dashboard\DTOs;

final class OverloadAnalysisDto
{
    /**
     * @param  MemberOverloadDto[]  $members
     */
    public function __construct(
        public readonly array $members,
        public readonly int $overloadedCount,
        public readonly string $referenceDate,
    ) {}
}
