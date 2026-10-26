<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Command\RecordAutomatedCommunication;

use App\Context\Client\Domain\ClientCommunication;
use App\Context\Client\Domain\Repository\ClientCommunicationRepositoryInterface;

/** Journal entries kept in memory, the message id unique as in the table. */
final class InMemoryClientCommunications implements ClientCommunicationRepositoryInterface
{
    /** @var array<string, ClientCommunication> */
    public array $entries = [];

    public int $saves = 0;

    public function save(ClientCommunication $clientCommunication): void
    {
        ++$this->saves;
        $this->entries[$clientCommunication->id()->toString()] = $clientCommunication;
    }

    public function addIfAbsent(ClientCommunication $automatedCommunication): bool
    {
        if (null !== $this->findByNotificationMessageId((string) $automatedCommunication->notificationMessageId())) {
            return false;
        }

        $this->entries[$automatedCommunication->id()->toString()] = $automatedCommunication;

        return true;
    }

    public function findByNotificationMessageId(string $notificationMessageId): ?ClientCommunication
    {
        foreach ($this->entries as $entry) {
            if ($notificationMessageId === $entry->notificationMessageId()) {
                return $entry;
            }
        }

        return null;
    }

    public function findByNotificationMessageIdForUpdate(string $notificationMessageId): ?ClientCommunication
    {
        return $this->findByNotificationMessageId($notificationMessageId);
    }
}
