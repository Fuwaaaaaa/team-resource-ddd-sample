<?php

declare(strict_types=1);

namespace App\Application\Admin\Exceptions;

use DomainException;

final class CannotChangeOwnRoleException extends DomainException
{
    public function __construct()
    {
        parent::__construct('You cannot change your own role.');
    }
}
