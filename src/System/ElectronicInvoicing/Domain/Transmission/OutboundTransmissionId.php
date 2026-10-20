<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Transmission;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class OutboundTransmissionId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
