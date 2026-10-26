<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Repository;

use App\Context\Client\Domain\ClientCommunication;

interface ClientCommunicationRepositoryInterface
{
    /** Inserts the entry, or stores the delivery status of an automated one. */
    public function save(ClientCommunication $clientCommunication): void;

    /** The automated entry of this Notification message, if journaled. */
    public function findByNotificationMessageId(string $notificationMessageId): ?ClientCommunication;
}
