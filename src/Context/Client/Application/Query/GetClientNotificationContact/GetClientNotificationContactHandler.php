<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientNotificationContact;

use App\Context\Client\Application\Port\ClientNotificationContactReaderInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class GetClientNotificationContactHandler
{
    public function __construct(
        private ClientNotificationContactReaderInterface $reader,
    ) {
    }

    /** Null for a client unknown to the clinic, a malformed id included. */
    public function __invoke(GetClientNotificationContact $query): ?ClientNotificationContact
    {
        if (!self::isUuid($query->clientId)) {
            return null;
        }

        $clientId = ClientId::fromString($query->clientId)->toString();

        return $this->reader->findForClients(ClinicId::fromString($query->clinicId), [$clientId])[$clientId] ?? null;
    }

    /** Whether the reader can look this id up (any UUID the record store accepts). */
    public static function isUuid(string $id): bool
    {
        try {
            Uuid::fromString(mb_trim($id));

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
