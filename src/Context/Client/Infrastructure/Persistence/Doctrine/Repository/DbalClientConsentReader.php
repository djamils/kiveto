<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Application\Port\ClientConsentReaderInterface;
use App\Context\Client\Application\Query\GetClientConsents\ClientConsentsView;
use App\Context\Client\Application\Query\GetClientConsents\ClientConsentView;
use App\Context\Client\Application\Query\ListClientConsentHistory\ConsentHistoryItemView;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ConsentKind;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

/**
 * The consents are read from the client's preferences document; the
 * history from its append-only table (dates stored in UTC).
 */
final readonly class DbalClientConsentReader implements ClientConsentReaderInterface
{
    private const string FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function consentsOf(ClinicId $clinicId, array $clientIds): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(id) AS id, status, communication_preferences FROM client__clients WHERE clinic_id = :clinicId AND id IN (:clientIds) ORDER BY id',
            [
                'clinicId'  => Uuid::fromString($clinicId->toString())->toBinary(),
                'clientIds' => self::binaries($clientIds),
            ],
            ['clientIds' => ArrayParameterType::BINARY],
        );

        $views = [];

        foreach ($rows as $row) {
            $id                            = self::text($row['id']);
            [$preferredChannel, $consents] = self::preferences($row['communication_preferences']);

            $views[$id] = new ClientConsentsView(
                clientId: $id,
                preferredChannel: $preferredChannel,
                archived: ClientStatus::ARCHIVED->value === $row['status'],
                consents: $consents,
            );
        }

        return $views;
    }

    public function historyOf(ClinicId $clinicId, array $clientIds, ?array $after, int $limit): array
    {
        $params = [
            'clinicId'  => Uuid::fromString($clinicId->toString())->toBinary(),
            'clientIds' => self::binaries($clientIds),
            'limit'     => $limit,
        ];
        $types = ['clientIds' => ArrayParameterType::BINARY, 'limit' => ParameterType::INTEGER];

        $keyset = '';
        if (null !== $after) {
            $keyset            = ' AND (at < :afterAt OR (at = :afterAt AND id < :afterId))';
            $params['afterAt'] = $after['at']->setTimezone(new \DateTimeZone('UTC'))->format(self::FORMAT);
            $params['afterId'] = Uuid::fromString($after['id'])->toBinary();
            $types['afterId']  = ParameterType::BINARY;
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(id) AS id, BIN_TO_UUID(client_id) AS client_id, kind, channel, action, at, source, actor_type,
                    BIN_TO_UUID(actor_id) AS actor_id, author_label, wording_version, approximate
             FROM client__consent_history
             WHERE clinic_id = :clinicId AND client_id IN (:clientIds)' . $keyset . '
             ORDER BY at DESC, id DESC
             LIMIT :limit',
            $params,
            $types,
        );

        return array_map(
            static fn (array $row): ConsentHistoryItemView => new ConsentHistoryItemView(
                id: self::text($row['id']),
                clientId: self::text($row['client_id']),
                kind: self::text($row['kind']),
                channel: self::text($row['channel']),
                action: self::text($row['action']),
                at: new \DateTimeImmutable(self::text($row['at']), new \DateTimeZone('UTC')),
                source: self::text($row['source']),
                actorType: self::text($row['actor_type']),
                actorId: \is_string($row['actor_id']) ? $row['actor_id'] : null,
                authorLabel: self::text($row['author_label']),
                wordingVersion: \is_string($row['wording_version']) ? $row['wording_version'] : null,
                approximate: (bool) $row['approximate'],
            ),
            $rows,
        );
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private static function binaries(array $ids): array
    {
        return array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $ids);
    }

    /**
     * Only the well-formed consents of a known kind are kept.
     *
     * @return array{0: ?string, 1: array<string, ClientConsentView>}
     */
    private static function preferences(mixed $json): array
    {
        $preferences = \is_string($json) ? json_decode($json, true, flags: \JSON_THROW_ON_ERROR) : null;

        if (!\is_array($preferences)) {
            return [null, []];
        }

        $consents = [];

        foreach (\is_array($preferences['consents'] ?? null) ? $preferences['consents'] : [] as $consent) {
            if (!\is_array($consent) || !\is_string($consent['kind'] ?? null) || !\is_array($consent['channels'] ?? null) || !\is_string($consent['grantedAt'] ?? null)) {
                continue;
            }

            $kind = ConsentKind::tryFrom($consent['kind']);

            if (null !== $kind) {
                $consents[$kind->value] = new ClientConsentView(
                    kind: $kind->value,
                    channels: array_values(array_filter($consent['channels'], \is_string(...))),
                    grantedAt: new \DateTimeImmutable($consent['grantedAt']),
                );
            }
        }

        return [\is_string($preferences['preferredChannel'] ?? null) ? $preferences['preferredChannel'] : null, $consents];
    }

    private static function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
