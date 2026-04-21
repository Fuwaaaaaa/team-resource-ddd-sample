<?php

declare(strict_types=1);

namespace App\Domain\Service;

final class OverloadAnalysis
{
    /** @var MemberOverloadEntry[] */
    private array $entries;

    /** @param MemberOverloadEntry[] $entries */
    public function __construct(array $entries)
    {
        $this->entries = $entries;
    }

    /** @return MemberOverloadEntry[] */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return MemberOverloadEntry[] 過負荷メンバーのみ */
    public function overloadedMembers(): array
    {
        return array_values(array_filter(
            $this->entries,
            fn (MemberOverloadEntry $e) => $e->isOverloaded()
        ));
    }

    public function hasOverloadedMembers(): bool
    {
        return count($this->overloadedMembers()) > 0;
    }
}
