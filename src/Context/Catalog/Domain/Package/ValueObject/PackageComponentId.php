<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Package\ValueObject;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class PackageComponentId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
