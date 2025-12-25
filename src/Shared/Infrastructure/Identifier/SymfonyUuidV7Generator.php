<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Identifier;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use Symfony\Component\Uid\UuidV7;

final class SymfonyUuidV7Generator implements UuidGeneratorInterface
{
    public function generate(): string
    {
        $uuid = new UuidV7();

        return $uuid->toString();
    }
}
