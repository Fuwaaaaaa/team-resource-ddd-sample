<?php

declare(strict_types=1);

namespace App\Domain\Member\Events;

use App\Domain\Member\MemberId;

final class MemberCreated
{
    private MemberId $memberId;

    public function __construct(MemberId $memberId)
    {
        $this->memberId = $memberId;
    }

    public function memberId(): MemberId
    {
        return $this->memberId;
    }
}
