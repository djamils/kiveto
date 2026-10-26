<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\Repository;

use App\Context\Client\Domain\ClientCommunication;

interface ClientCommunicationRepositoryInterface
{
    /** Inserts the entry, or stores the delivery status of an automated one. */
    public function save(ClientCommunication $clientCommunication): void;

    /**
     * Inserts an automated entry unless its Notification message is already
     * journaled (unique key, safe against a concurrent insert).
     *
     * @return bool whether the entry was inserted
     */
    public function addIfAbsent(ClientCommunication $automatedCommunication): bool;

    /** The automated entry of this Notification message, if journaled. */
    public function findByNotificationMessageId(string $notificationMessageId): ?ClientCommunication;

    /** Same, locked until the end of the transaction. */
    public function findByNotificationMessageIdForUpdate(string $notificationMessageId): ?ClientCommunication;
}
