<?php

declare(strict_types=1);

namespace App\Domain\Project\Exceptions;

use App\Domain\Project\ProjectStatus;
use DomainException;

final class InvalidProjectStatusTransition extends DomainException
{
    public static function from(ProjectStatus $current, ProjectStatus $next): self
    {
        return new self(sprintf(
            'Cannot transition project from %s to %s.',
            $current->value,
            $next->value,
        ));
    }
}
