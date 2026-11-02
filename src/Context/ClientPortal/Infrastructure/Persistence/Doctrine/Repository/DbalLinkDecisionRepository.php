<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Link\LinkDecision;
use App\Context\ClientPortal\Domain\Link\LinkDecisionId;
use App\Context\ClientPortal\Domain\Link\LinkDecisionOutcome;
use App\Context\ClientPortal\Domain\Link\LinkDecisionRepositoryInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

/** One row per « Rattachement » answer, written once; dates in UTC. */
final readonly class DbalLinkDecisionRepository implements LinkDecisionRepositoryInterface
{
    public const string TABLE = 'client_portal__link_decisions';

    public function __construct(private Connection $connection)
    {
    }

    public function save(LinkDecision $decision): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (id, clinic_id, user_id, outcome, client_ids, decided_at, decided_by_user_id, decided_by_name)'
            . ' VALUES (:id, :clinic, :user, :outcome, :clients, :decided, :by, :byName)',
            [
                'id'      => SqlValues::binary($decision->id()->toString()),
                'clinic'  => SqlValues::binary($decision->clinicId()),
                'user'    => SqlValues::binary($decision->userId()),
                'outcome' => $decision->outcome()->value,
                'clients' => json_encode($decision->clientIds(), \JSON_THROW_ON_ERROR),
                'decided' => SqlValues::utc($decision->decidedAt()),
                'by'      => SqlValues::binary($decision->decidedByUserId()),
                'byName'  => $decision->decidedByName(),
            ],
        );
    }

    public function findInClinic(string $clinicId, string $decisionId): ?LinkDecision
    {
        if (!Uuid::isValid($decisionId)) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE id = :id AND clinic_id = :clinic',
            ['id' => SqlValues::binary($decisionId), 'clinic' => SqlValues::binary($clinicId)],
        );

        return false === $row ? null : self::hydrate($row);
    }

    public function findByClinic(string $clinicId, int $limit): array
    {
        return array_map(self::hydrate(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE clinic_id = :clinic ORDER BY decided_at DESC, id DESC LIMIT :limit',
            ['clinic' => SqlValues::binary($clinicId), 'limit' => $limit],
            ['limit'  => ParameterType::INTEGER],
        ));
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): LinkDecision
    {
        return LinkDecision::reconstitute(
            id: LinkDecisionId::fromString(RowAccessor::uuid($row, 'id')),
            clinicId: RowAccessor::uuid($row, 'clinic_id'),
            userId: RowAccessor::uuid($row, 'user_id'),
            outcome: LinkDecisionOutcome::from(RowAccessor::string($row, 'outcome')),
            clientIds: SqlValues::stringList(RowAccessor::string($row, 'client_ids')),
            decidedAt: SqlValues::date(RowAccessor::string($row, 'decided_at')),
            decidedByUserId: RowAccessor::uuid($row, 'decided_by_user_id'),
            decidedByName: RowAccessor::string($row, 'decided_by_name'),
        );
    }
}
