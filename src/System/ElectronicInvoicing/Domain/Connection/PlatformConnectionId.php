<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Connection;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class PlatformConnectionId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
