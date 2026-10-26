<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Exception;

final class OutboundMessageNotFoundException extends \DomainException
{
    public function __construct(string $messageId)
    {
        parent::__construct(\sprintf('Outbound message "%s" not found.', $messageId));
    }
}
