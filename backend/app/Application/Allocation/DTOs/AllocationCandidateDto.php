<?php

declare(strict_types=1);

namespace App\Application\Allocation\DTOs;

use App\Domain\Service\AllocationCandidate;

final class AllocationCandidateDto
{
    /**
     * @param  string[]  $reasons
     * @param  RecentAssignmentDto[]  $recentAssignments
     */
    public function __construct(
        public readonly string $memberId,
        public readonly string $memberName,
        public readonly string $skillId,
        public readonly int $proficiency,
        public readonly int $availablePercentage,
        public readonly int $pastProjectExperienceCount,
        public readonly float $score,
        public readonly float $capacityScore,
        public readonly float $proficiencyScore,
        public readonly float $experienceScore,
        public readonly bool $nextWeekConflict,
        public readonly array $reasons,
        public readonly array $recentAssignments,
    ) {}

    /**
     * @param  RecentAssignmentDto[]  $recentAssignments
     */
    public static function fromDomain(AllocationCandidate $candidate, string $memberName, array $recentAssignments = []): self
    {
        return new self(
            memberId: $candidate->memberId()->toString(),
            memberName: $memberName,
            skillId: $candidate->skillId()->toString(),
            proficiency: $candidate->proficiency(),
            availablePercentage: $candidate->availablePercentage(),
            pastProjectExperienceCount: $candidate->pastProjectExperienceCount(),
            score: $candidate->score(),
            capacityScore: $candidate->capacityScore(),
            proficiencyScore: $candidate->proficiencyScore(),
            experienceScore: $candidate->experienceScore(),
            nextWeekConflict: $candidate->hasNextWeekConflict(),
            reasons: $candidate->reasons(),
            recentAssignments: $recentAssignments,
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
            'scoreBreakdown' => [
                'capacity' => $this->capacityScore,
                'proficiency' => $this->proficiencyScore,
                'experience' => $this->experienceScore,
            ],
            'nextWeekConflict' => $this->nextWeekConflict,
            'reasons' => $this->reasons,
            'recentAssignments' => array_map(fn (RecentAssignmentDto $a) => $a->toArray(), $this->recentAssignments),
        ];
    }
}
