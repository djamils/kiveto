<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

interface DeliveryStatusReaderInterface
{
    /**
     * @param list<string> $messageIds Notification message ids
     *
     * @return array<string, DeliveryStatus> message id => status; unknown ids are left out
     */
    public function statuses(array $messageIds): array;
}
