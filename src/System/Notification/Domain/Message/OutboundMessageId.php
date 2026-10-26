<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Message;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class OutboundMessageId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
