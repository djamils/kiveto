<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\ValueObject;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class PriceRuleId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
