<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Application\Verification\TaxProfileVerificationScheduleInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Cross-clinic on purpose (nightly run of the whole platform), like
 * ReindexSearchEntriesCommand. A null outcome means "not checked yet".
 */
final readonly class DbalTaxProfileVerificationSchedule implements TaxProfileVerificationScheduleInterface
{
    private const int CHUNK = 500;

    private const string DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private Connection $connection)
    {
    }

    public function findStale(\DateTimeImmutable $checkedBefore, \DateTimeImmutable $requestedBefore): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT BIN_TO_UUID(clinic_id) AS clinicId, BIN_TO_UUID(id) AS clientId
                FROM client__clients
                WHERE tax_status = 'french_business'
                  AND siren IS NOT NULL
                  AND status = 'active'
                  AND (tax_verification_requested_at IS NULL OR tax_verification_requested_at < :requestedBefore)
                  AND (
                      tax_company_checked_at IS NULL
                      OR tax_company_check_outcome IS NULL
                      OR tax_company_check_outcome = 'unavailable'
                      OR tax_vat_check_outcome IS NULL
                      OR tax_vat_check_outcome = 'unavailable'
                      OR tax_company_checked_at < :checkedBefore
                  )
                ORDER BY tax_company_checked_at IS NOT NULL, tax_company_checked_at, id
                SQL,
            [
                'requestedBefore' => $requestedBefore->format(self::DATE_FORMAT),
                'checkedBefore'   => $checkedBefore->format(self::DATE_FORMAT),
            ],
        );

        return array_map(static function (array $row): array {
            \assert(\is_string($row['clinicId']) && \is_string($row['clientId']));

            return ['clinicId' => $row['clinicId'], 'clientId' => $row['clientId']];
        }, $rows);
    }

    public function markRequested(array $clientIds, \DateTimeImmutable $now, \DateTimeImmutable $requestedBefore): array
    {
        $marked = [];

        foreach (array_chunk($clientIds, self::CHUNK) as $chunk) {
            $ids = array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $chunk);

            // The de-dup predicate is repeated: a request committed meanwhile is not doubled.
            // Bumping the version makes a concurrent sheet save conflict instead of erasing the marker.
            $this->connection->executeStatement(
                <<<'SQL'
                    UPDATE client__clients
                    SET tax_verification_requested_at = :now, version = version + 1
                    WHERE id IN (:ids)
                      AND (tax_verification_requested_at IS NULL OR tax_verification_requested_at < :requestedBefore)
                    SQL,
                ['now' => $now->format(self::DATE_FORMAT), 'ids' => $ids, 'requestedBefore' => $requestedBefore->format(self::DATE_FORMAT)],
                ['ids' => ArrayParameterType::BINARY],
            );

            $updated = $this->connection->fetchFirstColumn(
                'SELECT BIN_TO_UUID(id) FROM client__clients WHERE id IN (:ids) AND tax_verification_requested_at = :now',
                ['ids' => $ids, 'now' => $now->format(self::DATE_FORMAT)],
                ['ids' => ArrayParameterType::BINARY],
            );

            foreach ($updated as $id) {
                \assert(\is_string($id));
                $marked[] = $id;
            }
        }

        return $marked;
    }
}
