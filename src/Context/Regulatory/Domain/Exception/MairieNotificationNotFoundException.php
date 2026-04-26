<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\Exception;

final class MairieNotificationNotFoundException extends \DomainException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('MairieNotification "%s" not found.', $id));
    }
}
