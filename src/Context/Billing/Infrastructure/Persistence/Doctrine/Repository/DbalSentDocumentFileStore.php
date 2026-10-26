<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\SentDocumentFile;
use App\Context\Billing\Application\Port\SentDocumentFileStoreInterface;
use App\Context\Billing\Domain\Dispatch\SentDocumentKind;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * One row per document whose PDF was stored at its first send. The insert
 * is `INSERT IGNORE` and the re-read a locking read (`FOR SHARE`): under
 * REPEATABLE READ a plain SELECT would still see this transaction's snapshot,
 * without the row a concurrent first send committed in between.
 */
final readonly class DbalSentDocumentFileStore implements SentDocumentFileStoreInterface
{
    private const string TABLE = 'billing__sent_document_files';

    public function __construct(private Connection $connection)
    {
    }

    public function find(string $clinicId, string $documentId): ?SentDocumentFile
    {
        return $this->read($clinicId, $documentId, '');
    }

    public function record(string $clinicId, SentDocumentKind $kind, string $documentId, SentDocumentFile $file, \DateTimeImmutable $at): SentDocumentFile
    {
        $this->connection->executeStatement(
            'INSERT IGNORE INTO ' . self::TABLE . ' (document_id, clinic_id, document_kind, storage_key, filename, created_at)'
            . ' VALUES (:document, :clinic, :kind, :key, :filename, :at)',
            [
                'document' => Uuid::fromString($documentId)->toBinary(),
                'clinic'   => Uuid::fromString($clinicId)->toBinary(),
                'kind'     => $kind->value,
                'key'      => $file->storageKey,
                'filename' => $file->filename,
                'at'       => $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ],
        );

        return $this->read($clinicId, $documentId, ' FOR SHARE')
            ?? throw new \LogicException(\sprintf('The sent file of document %s belongs to another clinic.', $documentId));
    }

    private function read(string $clinicId, string $documentId, string $lock): ?SentDocumentFile
    {
        $row = $this->connection->fetchAssociative(
            'SELECT storage_key, filename FROM ' . self::TABLE . ' WHERE document_id = :document AND clinic_id = :clinic' . $lock,
            ['document' => Uuid::fromString($documentId)->toBinary(), 'clinic' => Uuid::fromString($clinicId)->toBinary()],
        );

        return false === $row ? null : new SentDocumentFile(RowAccessor::string($row, 'storage_key'), RowAccessor::string($row, 'filename'));
    }
}
