<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Application\Command\StorePublishedDocumentFile\StorePublishedDocumentFileHandler;
use App\Context\ClientPortal\Application\Port\DocumentPosition;
use App\Context\ClientPortal\Application\Port\ListedDocument;
use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;

/**
 * The account scope is a join on the confirmed links of the clinic, never a
 * list of client ids handed over by the caller: a revoked link of the same
 * clinic never leaks, and a later link shows the past documents.
 */
final readonly class DbalPublishedDocumentReadModel implements PublishedDocumentReadModelInterface
{
    private const string DOCUMENTS = DbalPublishedDocumentRepository::TABLE;
    private const string READS     = DbalDocumentReadRepository::TABLE;
    private const string LINKS     = 'client_portal__client_links';

    /** Listable: a served file of a document neither withdrawn nor waiting for a link. */
    private const string LISTABLE = "d.status IN ('" . PublicationStatus::AVAILABLE->value . "', '" . PublicationStatus::PENDING_FILE->value
        . "', '" . PublicationStatus::FAILED->value . "') AND d.file_version >= 1 AND d.storage_key IS NOT NULL";

    private const string SELECT = 'SELECT d.id, d.type, d.source_ref, d.title, d.pet_ids, d.issued_at, d.file_version, d.storage_key,'
        . ' d.practitioner_name, (r.document_id IS NULL) AS unread'
        . ' FROM ' . self::DOCUMENTS . ' d'
        . ' LEFT JOIN ' . self::READS . ' r ON r.document_id = d.id AND r.user_id = :user';

    private const string SCOPE = 'd.clinic_id = :clinic AND d.client_id IN (SELECT l.client_id FROM ' . self::LINKS
        . " l WHERE l.user_id = :user AND l.clinic_id = :clinic AND l.status = '" . ClientLinkStatus::CONFIRMED->value . "')";

    public function __construct(private Connection $connection)
    {
    }

    public function listable(string $userId, string $clinicId, ?string $type, ?string $petId, ?DocumentPosition $after, int $limit): array
    {
        $where      = [self::SCOPE, self::LISTABLE];
        $parameters = ['user' => SqlValues::binary($userId), 'clinic' => SqlValues::binary($clinicId)];

        if (null !== $type) {
            $where[]            = 'd.type = :type';
            $parameters['type'] = $type;
        }

        if (null !== $petId) {
            $where[]           = 'JSON_CONTAINS(d.pet_ids, JSON_QUOTE(:pet))';
            $parameters['pet'] = $petId;
        }

        if (null !== $after) {
            $where[]               = '(d.issued_at < :afterAt OR (d.issued_at = :afterAt AND d.id < :afterId))';
            $parameters['afterAt'] = SqlValues::utc($after->issuedAt);
            $parameters['afterId'] = SqlValues::binary($after->id);
        }

        $rows = $this->connection->fetchAllAssociative(
            self::SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY d.issued_at DESC, d.id DESC LIMIT ' . max(1, $limit),
            $parameters,
        );

        return array_map(self::hydrate(...), $rows);
    }

    public function find(string $userId, string $clinicId, string $documentId): ?ListedDocument
    {
        return $this->one('d.id = :id', ['id' => SqlValues::binary($documentId)], $userId, $clinicId);
    }

    public function findBySource(string $userId, string $clinicId, string $type, string $sourceRef): ?ListedDocument
    {
        return $this->one('d.type = :type AND d.source_ref = :source', ['type' => $type, 'source' => SqlValues::binary($sourceRef)], $userId, $clinicId);
    }

    public function versionKey(string $clinicId, string $documentId, int $version): ?string
    {
        $row = $this->connection->fetchAssociative(
            'SELECT d.type, d.file_version, d.storage_key FROM ' . self::DOCUMENTS . ' d'
            . ' WHERE d.id = :id AND d.clinic_id = :clinic AND ' . self::LISTABLE,
            ['id' => SqlValues::binary($documentId), 'clinic' => SqlValues::binary($clinicId)],
        );

        if (false === $row) {
            return null;
        }

        $served = RowAccessor::int($row, 'file_version');
        $key    = RowAccessor::string($row, 'storage_key');

        if ($version === $served) {
            return $key;
        }

        // An earlier version of a re-shared document, until its delayed deletion.
        $versioned = DocumentType::from(RowAccessor::string($row, 'type'))->hasPortalFile();

        return $versioned && $version < $served ? StorePublishedDocumentFileHandler::storageKey($documentId, $version) : null;
    }

    public function reportSharedAt(string $clinicId, string $consultationId): ?\DateTimeImmutable
    {
        $updatedAt = $this->connection->fetchOne(
            'SELECT d.updated_at FROM ' . self::DOCUMENTS . ' d'
            . ' WHERE d.clinic_id = :clinic AND d.type = :type AND d.source_ref = :source AND d.status <> :withdrawn',
            [
                'clinic'    => SqlValues::binary($clinicId),
                'type'      => DocumentType::REPORT->value,
                'source'    => SqlValues::binary($consultationId),
                'withdrawn' => PublicationStatus::WITHDRAWN->value,
            ],
        );

        return \is_string($updatedAt) ? SqlValues::date($updatedAt) : null;
    }

    public function unreadPerClinic(string $userId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT d.clinic_id, COUNT(*) AS unread FROM ' . self::DOCUMENTS . ' d'
            . ' JOIN ' . self::LINKS . " l ON l.clinic_id = d.clinic_id AND l.client_id = d.client_id AND l.user_id = :user AND l.status = '"
            . ClientLinkStatus::CONFIRMED->value . "'"
            . ' LEFT JOIN ' . self::READS . ' r ON r.document_id = d.id AND r.user_id = :user'
            . ' WHERE r.document_id IS NULL AND ' . self::LISTABLE
            . ' GROUP BY d.clinic_id',
            ['user' => SqlValues::binary($userId)],
        );

        $counts = [];

        foreach ($rows as $row) {
            $counts[RowAccessor::uuid($row, 'clinic_id')] = RowAccessor::int($row, 'unread');
        }

        return $counts;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function one(string $condition, array $parameters, string $userId, string $clinicId): ?ListedDocument
    {
        $row = $this->connection->fetchAssociative(
            self::SELECT . ' WHERE ' . self::SCOPE . ' AND ' . self::LISTABLE . ' AND ' . $condition . ' LIMIT 1',
            ['user' => SqlValues::binary($userId), 'clinic' => SqlValues::binary($clinicId), ...$parameters],
        );

        return false === $row ? null : self::hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): ListedDocument
    {
        return new ListedDocument(
            id: RowAccessor::uuid($row, 'id'),
            type: RowAccessor::string($row, 'type'),
            sourceRef: RowAccessor::uuid($row, 'source_ref'),
            title: RowAccessor::string($row, 'title'),
            petIds: SqlValues::stringList(RowAccessor::string($row, 'pet_ids')),
            issuedAt: SqlValues::date(RowAccessor::string($row, 'issued_at')),
            unread: 1 === RowAccessor::int($row, 'unread'),
            fileVersion: RowAccessor::int($row, 'file_version'),
            storageKey: RowAccessor::string($row, 'storage_key'),
            practitionerName: RowAccessor::nullableString($row, 'practitioner_name'),
        );
    }
}
