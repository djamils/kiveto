<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Inbox;

use App\Shared\Domain\Identifier\AbstractUuidId;

final class InAppNotificationId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
