<?php

declare(strict_types=1);

namespace App\Application\Allocation\DTOs;

use App\Domain\Service\AllocationCandidate;

final class AllocationCandidateDto
{
    /** @param string[] $reasons */
    public function __construct(
        public readonly string $memberId,
        public readonly string $memberName,
        public readonly string $skillId,
        public readonly int $proficiency,
        public readonly int $availablePercentage,
        public readonly int $pastProjectExperienceCount,
        public readonly float $score,
        public readonly array $reasons,
    ) {}

    public static function fromDomain(AllocationCandidate $candidate, string $memberName): self
    {
        return new self(
            memberId: $candidate->memberId()->toString(),
            memberName: $memberName,
            skillId: $candidate->skillId()->toString(),
            proficiency: $candidate->proficiency(),
            availablePercentage: $candidate->availablePercentage(),
            pastProjectExperienceCount: $candidate->pastProjectExperienceCount(),
            score: $candidate->score(),
            reasons: $candidate->reasons(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'memberId' => $this->memberId,
            'memberName' => $this->memberName,
            'skillId' => $this->skillId,
            'proficiency' => $this->proficiency,
            'availablePercentage' => $this->availablePercentage,
            'pastProjectExperienceCount' => $this->pastProjectExperienceCount,
            'score' => $this->score,
            'reasons' => $this->reasons,
        ];
    }
}
