<?php

declare(strict_types=1);

namespace App\Domain\AllocationChangeRequest;

interface AllocationChangeRequestRepositoryInterface
{
    public function findById(AllocationChangeRequestId $id): ?AllocationChangeRequest;

    /**
     * @param  ChangeRequestStatus|null  $status  null → 全ステータス
     * @param  int|null  $requestedBy  null → 全ユーザー
     * @return AllocationChangeRequest[]
     */
    public function findList(?ChangeRequestStatus $status = null, ?int $requestedBy = null): array;

    public function save(AllocationChangeRequest $request): void;

    public function nextIdentity(): AllocationChangeRequestId;
}
