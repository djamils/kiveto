<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Repository;

use App\System\ElectronicInvoicing\Application\Port\SubmissionScheduleInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalSubmissionSchedule implements SubmissionScheduleInterface
{
    private const string DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private Connection $connection)
    {
    }

    public function preparedWithoutConnection(string $organisationId): array
    {
        return $this->ids(
            "SELECT BIN_TO_UUID(id) FROM electronic_invoicing__outbound_transmissions
             WHERE seller_siren = :organisation AND state = 'prepared' AND connection_id IS NULL
             ORDER BY kind = 'credit_note', document_date, document_number",
            ['organisation' => $organisationId],
        );
    }

    public function staleRequested(\DateTimeImmutable $requestedBefore): array
    {
        return $this->ids(
            "SELECT BIN_TO_UUID(id) FROM electronic_invoicing__outbound_transmissions
             WHERE connection_id IS NOT NULL AND state IN ('prepared', 'submitting')
               AND (submission_requested_at IS NULL OR submission_requested_at <= :before)
             ORDER BY kind = 'credit_note', document_date, document_number",
            ['before' => $requestedBefore->format(self::DATE_FORMAT)],
        );
    }

    public function organisationsWaitingForConnection(): array
    {
        return $this->ids(
            "SELECT DISTINCT seller_siren FROM electronic_invoicing__outbound_transmissions
             WHERE state = 'prepared' AND connection_id IS NULL ORDER BY seller_siren",
            [],
        );
    }

    public function markSubmissionRequested(array $transmissionIds, \DateTimeImmutable $now, \DateTimeImmutable $requestedBefore): array
    {
        $marked = [];

        foreach ($transmissionIds as $id) {
            // Each row is marked on its own, the predicate repeated: a
            // transmission is sent only if this very statement marked it, so
            // two concurrent releases never send it twice. The version is
            // bumped like any change of the aggregate.
            $updated = $this->connection->executeStatement(
                "UPDATE electronic_invoicing__outbound_transmissions
                 SET submission_requested_at = :now, submission_attempts = submission_attempts + 1, version = version + 1
                 WHERE id = :id AND state IN ('prepared', 'submitting')
                   AND (submission_requested_at IS NULL OR submission_requested_at <= :before)",
                ['now' => $now->format(self::DATE_FORMAT), 'id' => Uuid::fromString($id)->toBinary(), 'before' => $requestedBefore->format(self::DATE_FORMAT)],
            );

            if (1 === $updated) {
                $marked[] = $id;
            }
        }

        return $marked;
    }

    /**
     * @param array<string, string> $params
     *
     * @return list<string>
     */
    private function ids(string $sql, array $params): array
    {
        return array_map(static fn (mixed $id): string => \is_scalar($id) ? (string) $id : '', $this->connection->fetchFirstColumn($sql, $params));
    }
}
