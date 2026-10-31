<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Document\DocumentReadRepositoryInterface;
use Doctrine\DBAL\Connection;

final readonly class DbalDocumentReadRepository implements DocumentReadRepositoryInterface
{
    public const string TABLE = 'client_portal__document_reads';

    public function __construct(private Connection $connection)
    {
    }

    public function markRead(string $documentId, string $userId, \DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'INSERT IGNORE INTO ' . self::TABLE . ' (document_id, user_id, read_at) VALUES (:document, :user, :at)',
            ['document' => SqlValues::binary($documentId), 'user' => SqlValues::binary($userId), 'at' => SqlValues::utc($at)],
        );
    }

    public function forgetReads(string $documentId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE document_id = :document',
            ['document' => SqlValues::binary($documentId)],
        );
    }
}
