<?php

declare(strict_types=1);

namespace App\Application\Admin\Exceptions;

use DomainException;

final class CannotResetDisabledUserException extends DomainException
{
    public function __construct()
    {
        parent::__construct('You cannot reset the password of a disabled account.');
    }
}
