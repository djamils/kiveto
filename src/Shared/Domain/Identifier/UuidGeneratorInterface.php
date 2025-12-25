<?php

declare(strict_types=1);

namespace App\Shared\Domain\Identifier;

interface UuidGeneratorInterface
{
    public function generate(): string;
}
