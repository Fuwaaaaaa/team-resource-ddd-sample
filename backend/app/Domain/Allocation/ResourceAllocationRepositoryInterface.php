<?php

declare(strict_types=1);

namespace App\Domain\Allocation;

use App\Domain\Member\MemberId;
use App\Domain\Project\ProjectId;
use DateTimeImmutable;

interface ResourceAllocationRepositoryInterface
{
    public function findById(AllocationId $id): ?ResourceAllocation;

    /** @return ResourceAllocation[] */
    public function findByProjectId(ProjectId $projectId): array;

    /** @return ResourceAllocation[] */
    public function findByMemberId(MemberId $memberId): array;

    /** @return ResourceAllocation[] */
    public function findActiveOnDate(DateTimeImmutable $date): array;

    public function save(ResourceAllocation $allocation): void;

    public function nextIdentity(): AllocationId;
}
