<?php

declare(strict_types=1);

namespace App\Application\Project\Commands;

final class CreateProjectCommand
{
    public function __construct(
        public readonly string $name,
    ) {}
}
