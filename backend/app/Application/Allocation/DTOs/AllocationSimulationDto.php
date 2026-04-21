<?php

declare(strict_types=1);

namespace App\Application\Allocation\DTOs;

final class AllocationSimulationDto
{
    public function __construct(
        public readonly AllocationDto $wouldCreate,
        /** 現時点での合計割当 % (revoked 除く、referenceDate でアクティブなもののみ) */
        public readonly int $currentTotalPercentage,
        /** 作成後に予想される合計割当 % */
        public readonly int $projectedTotalPercentage,
        public readonly int $projectedAvailablePercentage,
        public readonly bool $projectedOverloaded,
        public readonly float $projectedOverloadHours,
    ) {}
}
