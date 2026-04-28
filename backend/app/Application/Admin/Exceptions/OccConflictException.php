<?php

declare(strict_types=1);

namespace App\Application\Admin\Exceptions;

use DomainException;

final class OccConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct('This user was edited by someone else. Please reload and try again.');
    }
}
