<?php

declare(strict_types=1);

namespace App\ClinicAccess\Domain\ValueObject;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class MembershipId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
