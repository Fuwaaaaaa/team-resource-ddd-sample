<?php

declare(strict_types=1);

namespace App\Domain\AllocationChangeRequest;

use InvalidArgumentException;

/**
 * 変更申請のペイロード (JSON 化可能な純粋データ)。
 *
 * create_allocation:
 *   memberId, projectId, skillId, allocationPercentage, periodStart (Y-m-d), periodEnd (Y-m-d)
 * revoke_allocation:
 *   allocationId
 */
final class ChangeRequestPayload
{
    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data) {}

    /** @param array<string, mixed> $data */
    public static function forCreateAllocation(array $data): self
    {
        foreach (['memberId', 'projectId', 'skillId', 'allocationPercentage', 'periodStart', 'periodEnd'] as $required) {
            if (! array_key_exists($required, $data)) {
                throw new InvalidArgumentException("create_allocation payload missing key: {$required}");
            }
        }

        return new self($data);
    }

    /** @param array<string, mixed> $data */
    public static function forRevokeAllocation(array $data): self
    {
        if (! array_key_exists('allocationId', $data)) {
            throw new InvalidArgumentException('revoke_allocation payload missing key: allocationId');
        }

        return new self($data);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }
}
