<?php

declare(strict_types=1);

namespace App\Application\Admin\Exceptions;

use DomainException;

final class CannotDisableSelfException extends DomainException
{
    public function __construct()
    {
        parent::__construct('You cannot disable your own account.');
    }
}
