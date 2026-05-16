<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class MarketingAuthorizationId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
