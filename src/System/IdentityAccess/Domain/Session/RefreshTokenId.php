<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Session;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class RefreshTokenId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
