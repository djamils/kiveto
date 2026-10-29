<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Dispatch;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class InvoiceDispatchId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
