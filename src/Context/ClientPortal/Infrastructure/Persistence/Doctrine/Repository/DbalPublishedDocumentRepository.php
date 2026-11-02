<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;

/**
 * One row per (clinic, type, source) — unique key
 * `uniq_published_document_source`. Dates are written in UTC.
 */
final readonly class DbalPublishedDocumentRepository implements PublishedDocumentRepositoryInterface
{
    public const string TABLE = 'client_portal__published_documents';

    private const string LINKS = 'client_portal__client_links';

    private const string COLUMNS = 'id, clinic_id, client_id, type, source_ref, consultation_id, patient_id, pet_ids, title, issued_at,'
        . ' practitioner_name, storage_key, file_version, pending_file_version, render_attempts, status, published_at, updated_at, shared_at';

    private const string VALUES = ':id, :clinic, :client, :type, :source, :consultation, :patient, :pets, :title, :issued,'
        . ' :practitioner, :storage, :version, :pending, :attempts, :status, :published, :updated, :shared';

    /** Updated by save(), `status` last so that every guard reads the stored status. */
    private const array UPDATED_COLUMNS = [
        'client_id', 'consultation_id', 'patient_id', 'pet_ids', 'title', 'issued_at', 'practitioner_name', 'storage_key',
        'file_version', 'pending_file_version', 'render_attempts', 'updated_at', 'shared_at', 'status',
    ];

    public function __construct(private Connection $connection)
    {
    }

    public function save(PublishedDocument $document): void
    {
        // A withdrawn row is terminal: a writer that loaded it before the
        // withdrawal committed never brings it back (every column keeps its
        // value, `status` being assigned last as MySQL assigns left to right).
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (' . self::COLUMNS . ') VALUES (' . self::VALUES . ')'
            . ' AS new ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(
                static fn (string $column): string => $column . ' = IF(' . self::TABLE . '.status = :withdrawn, ' . self::TABLE . '.' . $column . ', new.' . $column . ')',
                self::UPDATED_COLUMNS,
            )),
            [...self::parameters($document), 'withdrawn' => PublicationStatus::WITHDRAWN->value],
        );
    }

    public function insertIfAbsent(PublishedDocument $document): bool
    {
        return 1 === $this->connection->executeStatement(
            'INSERT IGNORE INTO ' . self::TABLE . ' (' . self::COLUMNS . ') VALUES (' . self::VALUES . ')',
            self::parameters($document),
        );
    }

    public function findForUpdate(string $clinicId, DocumentType $type, string $sourceRef): ?PublishedDocument
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE clinic_id = :clinic AND type = :type AND source_ref = :source FOR UPDATE',
            ['clinic' => SqlValues::binary($clinicId), 'type' => $type->value, 'source' => SqlValues::binary($sourceRef)],
        );

        return false === $row ? null : self::hydrate($row);
    }

    public function findByIdForUpdate(string $documentId): ?PublishedDocument
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = :id FOR UPDATE',
            ['id' => SqlValues::binary($documentId)],
        );

        return false === $row ? null : self::hydrate($row);
    }

    public function isServedKey(string $storageKey): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM ' . self::TABLE . ' WHERE storage_key = :key LIMIT 1',
            ['key' => $storageKey],
        );
    }

    public function findAwaitingLinkForUpdate(string $clinicId, string $clientId, int $limit): array
    {
        return $this->list(
            'clinic_id = :clinic AND client_id = :client AND status = :status ORDER BY published_at ASC, id ASC LIMIT ' . $limit . ' FOR UPDATE',
            ['clinic' => SqlValues::binary($clinicId), 'client' => SqlValues::binary($clientId), 'status' => PublicationStatus::AWAITING_LINK->value],
        );
    }

    public function findStalePendingFilesForUpdate(\DateTimeImmutable $updatedBefore, int $limit): array
    {
        return $this->list(
            'status = :status AND updated_at < :before AND render_attempts < ' . PublishedDocument::MAX_RENDER_ATTEMPTS
            . ' ORDER BY updated_at ASC, id ASC LIMIT ' . $limit . ' FOR UPDATE SKIP LOCKED',
            ['status' => PublicationStatus::PENDING_FILE->value, 'before' => SqlValues::utc($updatedBefore)],
        );
    }

    public function findStaleReleasableForUpdate(\DateTimeImmutable $updatedBefore, int $limit): array
    {
        return $this->list(
            'status = :status AND updated_at < :before AND EXISTS (SELECT 1 FROM ' . self::LINKS . ' l'
            . ' WHERE l.clinic_id = ' . self::TABLE . '.clinic_id AND l.client_id = ' . self::TABLE . '.client_id AND l.status = :confirmed)'
            . ' ORDER BY updated_at ASC, id ASC LIMIT ' . $limit . ' FOR UPDATE SKIP LOCKED',
            [
                'status'    => PublicationStatus::AWAITING_LINK->value,
                'before'    => SqlValues::utc($updatedBefore),
                'confirmed' => ClientLinkStatus::CONFIRMED->value,
            ],
        );
    }

    public function findByPatientForUpdate(string $clinicId, string $patientId): array
    {
        return $this->list(
            'clinic_id = :clinic AND patient_id = :patient ORDER BY id ASC FOR UPDATE',
            ['clinic' => SqlValues::binary($clinicId), 'patient' => SqlValues::binary($patientId)],
        );
    }

    /**
     * @param array<string, string> $parameters
     *
     * @return list<PublishedDocument>
     */
    private function list(string $where, array $parameters): array
    {
        return array_map(
            self::hydrate(...),
            $this->connection->fetchAllAssociative('SELECT * FROM ' . self::TABLE . ' WHERE ' . $where, $parameters),
        );
    }

    /**
     * @return array<string, string|int|null>
     */
    private static function parameters(PublishedDocument $document): array
    {
        return [
            'id'           => SqlValues::binary($document->id()->toString()),
            'clinic'       => SqlValues::binary($document->clinicId()),
            'client'       => SqlValues::nullableBinary($document->clientId()),
            'type'         => $document->type()->value,
            'source'       => SqlValues::binary($document->sourceRef()),
            'consultation' => SqlValues::nullableBinary($document->consultationId()),
            'patient'      => SqlValues::nullableBinary($document->patientId()),
            'pets'         => json_encode($document->petIds(), \JSON_THROW_ON_ERROR),
            'title'        => $document->title(),
            'issued'       => SqlValues::utc($document->issuedAt()),
            'practitioner' => $document->practitionerName(),
            'storage'      => $document->storageKey(),
            'version'      => $document->fileVersion(),
            'pending'      => $document->pendingFileVersion(),
            'attempts'     => $document->renderAttempts(),
            'status'       => $document->status()->value,
            'published'    => SqlValues::utc($document->publishedAt()),
            'updated'      => SqlValues::utc($document->updatedAt()),
            'shared'       => SqlValues::utc($document->sharedAt()),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): PublishedDocument
    {
        return PublishedDocument::reconstitute(
            id: PublishedDocumentId::fromString(RowAccessor::uuid($row, 'id')),
            clinicId: RowAccessor::uuid($row, 'clinic_id'),
            clientId: RowAccessor::nullableUuid($row, 'client_id'),
            type: DocumentType::from(RowAccessor::string($row, 'type')),
            sourceRef: RowAccessor::uuid($row, 'source_ref'),
            consultationId: RowAccessor::nullableUuid($row, 'consultation_id'),
            patientId: RowAccessor::nullableUuid($row, 'patient_id'),
            petIds: SqlValues::stringList(RowAccessor::string($row, 'pet_ids')),
            title: RowAccessor::string($row, 'title'),
            issuedAt: SqlValues::date(RowAccessor::string($row, 'issued_at')),
            practitionerName: RowAccessor::nullableString($row, 'practitioner_name'),
            storageKey: RowAccessor::nullableString($row, 'storage_key'),
            fileVersion: RowAccessor::int($row, 'file_version'),
            pendingFileVersion: RowAccessor::nullableInt($row, 'pending_file_version'),
            renderAttempts: RowAccessor::int($row, 'render_attempts'),
            status: PublicationStatus::from(RowAccessor::string($row, 'status')),
            publishedAt: SqlValues::date(RowAccessor::string($row, 'published_at')),
            updatedAt: SqlValues::date(RowAccessor::string($row, 'updated_at')),
            sharedAt: SqlValues::date(RowAccessor::string($row, 'shared_at')),
        );
    }
}
