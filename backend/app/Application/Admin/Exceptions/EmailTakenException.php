<?php

declare(strict_types=1);

namespace App\Application\Admin\Exceptions;

use DomainException;

final class EmailTakenException extends DomainException
{
    public function __construct(string $email)
    {
        parent::__construct(sprintf('Email "%s" is already taken.', $email));
    }
}
