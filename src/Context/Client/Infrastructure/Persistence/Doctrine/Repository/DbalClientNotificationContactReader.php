<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Application\Port\ClientNotificationContactReaderInterface;
use App\Context\Client\Application\Query\GetClientNotificationContact\ClientNotificationContact;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * One query for any number of clients: each client row joined to its
 * contact methods, primary ones first, then in record order (UUID v7).
 */
final readonly class DbalClientNotificationContactReader implements ClientNotificationContactReaderInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function findForClients(ClinicId $clinicId, array $clientIds): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT BIN_TO_UUID(c.id) AS id, c.first_name, c.last_name, c.legal_name, c.civility, c.status,
                       c.communication_preferences, cm.type, cm.label, cm.value, cm.is_primary
                FROM client__clients c
                LEFT JOIN client__contact_methods cm ON cm.client_id = c.id
                WHERE c.clinic_id = :clinicId AND c.id IN (:clientIds)
                ORDER BY c.id, cm.id
                SQL,
            [
                'clinicId'  => Uuid::fromString($clinicId->toString())->toBinary(),
                'clientIds' => array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $clientIds),
            ],
            ['clientIds' => ArrayParameterType::BINARY],
        );

        /** @var array<string, array{client: array<string, mixed>, methods: list<array{type: string, label: string, value: string, isPrimary: bool}>}> $clients */
        $clients = [];

        foreach ($rows as $row) {
            $id = self::text($row['id']);
            $clients[$id] ??= ['client' => $row, 'methods' => []];

            if (null !== $row['type']) {
                $clients[$id]['methods'][] = [
                    'type'      => self::text($row['type']),
                    'label'     => self::text($row['label']),
                    'value'     => self::text($row['value']),
                    'isPrimary' => (bool) $row['is_primary'],
                ];
            }
        }

        $contacts = [];

        foreach ($clients as $id => ['client' => $client, 'methods' => $methods]) {
            [$preferredChannel, $consents] = self::preferences($client['communication_preferences']);

            $contacts[$id] = ClientNotificationContact::compose(
                clientId: $id,
                firstName: self::text($client['first_name']),
                lastName: self::text($client['last_name']),
                legalName: \is_string($client['legal_name']) ? $client['legal_name'] : null,
                civility: self::text($client['civility']),
                archived: ClientStatus::ARCHIVED->value === $client['status'],
                contactMethods: $methods,
                preferredChannel: $preferredChannel,
                consents: $consents,
            );
        }

        return $contacts;
    }

    /**
     * @return array{0: ?string, 1: array<string, list<string>>}
     */
    private static function preferences(mixed $json): array
    {
        $preferences = \is_string($json) ? json_decode($json, true, flags: \JSON_THROW_ON_ERROR) : null;

        if (!\is_array($preferences)) {
            return [null, []];
        }

        $consents = [];

        foreach (\is_array($preferences['consents'] ?? null) ? $preferences['consents'] : [] as $consent) {
            if (\is_array($consent) && \is_string($consent['kind'] ?? null) && \is_array($consent['channels'] ?? null)) {
                $consents[$consent['kind']] = array_values(array_filter($consent['channels'], \is_string(...)));
            }
        }

        return [\is_string($preferences['preferredChannel'] ?? null) ? $preferences['preferredChannel'] : null, $consents];
    }

    private static function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
