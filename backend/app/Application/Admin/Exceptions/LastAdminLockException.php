<?php

declare(strict_types=1);

namespace App\Application\Admin\Exceptions;

use DomainException;

final class LastAdminLockException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Cannot demote the last remaining admin.');
    }
}
