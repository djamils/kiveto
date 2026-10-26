<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Billing\Application\Port\TransmissionStatusProjectionInterface;
use App\Context\Billing\Application\Port\TransmissionStatusUpdate;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalTransmissionStatusProjection implements TransmissionStatusProjectionInterface
{
    private const string DATE_FORMAT = 'Y-m-d H:i:s';

    private const array TABLES = [
        self::KIND_INVOICE     => 'billing__invoices',
        self::KIND_CREDIT_NOTE => 'billing__credit_notes',
    ];

    public function __construct(private Connection $connection)
    {
    }

    public function markRequested(string $documentId, string $kind, array $document, \DateTimeImmutable $now): void
    {
        $this->connection->executeStatement(
            \sprintf(
                'UPDATE %s SET transmission_state = :state, transmission_document = :document, transmission_sequence = 0, transmission_updated_at = :now
                 WHERE id = :id AND transmission_state IS NULL',
                self::TABLES[$kind],
            ),
            [
                'state'    => self::REQUESTED,
                'document' => json_encode($document, \JSON_THROW_ON_ERROR),
                'now'      => $now->format(self::DATE_FORMAT),
                'id'       => Uuid::fromString($documentId)->toBinary(),
            ],
        );
    }

    public function frozenDocument(string $documentId, string $kind): ?array
    {
        $document = $this->connection->fetchOne(
            \sprintf('SELECT transmission_document FROM %s WHERE id = :id', self::TABLES[$kind]),
            ['id' => Uuid::fromString($documentId)->toBinary()],
        );

        if (!\is_string($document)) {
            return null;
        }

        $decoded = json_decode($document, true, 512, \JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? array_combine(array_map(strval(...), array_keys($decoded)), $decoded) : null;
    }

    public function markRequestFailed(string $documentId, string $kind, \DateTimeImmutable $now): void
    {
        $this->connection->executeStatement(
            \sprintf(
                'UPDATE %s SET transmission_state = :failed, transmission_requires_action = 1, transmission_updated_at = :now
                 WHERE id = :id AND transmission_state = :requested',
                self::TABLES[$kind],
            ),
            ['failed' => self::REQUEST_FAILED, 'requested' => self::REQUESTED, 'now' => $now->format(self::DATE_FORMAT), 'id' => Uuid::fromString($documentId)->toBinary()],
        );
    }

    public function apply(TransmissionStatusUpdate $update): bool
    {
        foreach (self::TABLES as $table) {
            $changed = $this->connection->executeStatement(
                \sprintf(
                    'UPDATE %s SET transmission_state = :state, transmission_block_reason = :blockReason,
                            transmission_status_code = :code, transmission_status_label = :label,
                            transmission_reason_code = :reasonCode, transmission_reason = :reason,
                            transmission_requires_action = :action, transmission_sequence = :sequence,
                            transmission_updated_at = :at
                     WHERE id = :id AND (transmission_sequence IS NULL OR transmission_sequence < :sequence)',
                    $table,
                ),
                [
                    'state'       => $update->state,
                    'blockReason' => $update->blockReason,
                    'code'        => $update->statusCode,
                    'label'       => $update->statusLabel,
                    'reasonCode'  => $update->reasonCode,
                    'reason'      => null !== $update->reasonText ? mb_substr($update->reasonText, 0, 512) : null,
                    'action'      => $update->requiresAction ? 1 : 0,
                    'sequence'    => $update->sequence,
                    'at'          => $update->updatedAt->format(self::DATE_FORMAT),
                    'id'          => Uuid::fromString($update->documentId)->toBinary(),
                ],
            );

            if ($changed > 0) {
                return true;
            }
        }

        return false;
    }

    public function stillRequested(\DateTimeImmutable $issuedBefore): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(clinic_id) AS clinic_id, BIN_TO_UUID(id) AS id, 'invoice' AS kind, issued_at
             FROM billing__invoices WHERE transmission_state = :requested AND issued_at <= :before
             UNION ALL
             SELECT BIN_TO_UUID(clinic_id), BIN_TO_UUID(id), 'credit_note', issued_at
             FROM billing__credit_notes WHERE transmission_state = :requested AND issued_at <= :before
             ORDER BY issued_at",
            ['requested' => self::REQUESTED, 'before' => $issuedBefore->format(self::DATE_FORMAT)],
        );

        return array_map(static fn (array $row): array => [
            'clinicId'   => RowAccessor::string($row, 'clinic_id'),
            'documentId' => RowAccessor::string($row, 'id'),
            'kind'       => RowAccessor::string($row, 'kind'),
            'issuedAt'   => new \DateTimeImmutable(RowAccessor::string($row, 'issued_at')),
        ], $rows);
    }
}
