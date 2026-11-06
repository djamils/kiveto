<?php

declare(strict_types=1);

namespace App\System\Audit\Domain\ValueObject;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class AuditEntryId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
