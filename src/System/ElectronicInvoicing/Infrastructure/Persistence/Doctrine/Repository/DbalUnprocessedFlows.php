<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\System\ElectronicInvoicing\Application\Port\UnprocessedFlowsInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalUnprocessedFlows implements UnprocessedFlowsInterface
{
    public function __construct(
        private Connection $connection,
        private UuidGeneratorInterface $uuids,
    ) {
    }

    public function record(string $organisationId, string $flowId, string $reason, ?string $error, ?string $content, \DateTimeImmutable $now): int
    {
        // A flow read once keeps its content when a later run cannot read it.
        $this->connection->executeStatement(
            'INSERT INTO electronic_invoicing__unprocessed_flows (id, organisation_id, flow_id, reason, error, content, attempts, last_seen_at)
             VALUES (:id, :organisation, :flow, :reason, :error, :content, 1, :now)
             ON DUPLICATE KEY UPDATE attempts = attempts + 1, reason = VALUES(reason), error = VALUES(error),
                content = COALESCE(VALUES(content), content), last_seen_at = VALUES(last_seen_at)',
            [
                'id'           => Uuid::fromString($this->uuids->generate())->toBinary(),
                'organisation' => $organisationId,
                'flow'         => $flowId,
                'reason'       => $reason,
                'error'        => $error,
                'content'      => $content,
                'now'          => $now->format('Y-m-d H:i:s'),
            ],
        );

        $attempts = $this->connection->fetchOne(
            'SELECT attempts FROM electronic_invoicing__unprocessed_flows WHERE organisation_id = :organisation AND flow_id = :flow',
            ['organisation' => $organisationId, 'flow' => $flowId],
        );

        return is_numeric($attempts) ? (int) $attempts : 0;
    }

    public function toRetry(string $organisationId, int $maxAttempts): array
    {
        return array_map(
            static fn (mixed $flowId): string => \is_scalar($flowId) ? (string) $flowId : '',
            $this->connection->fetchFirstColumn(
                'SELECT flow_id FROM electronic_invoicing__unprocessed_flows
                 WHERE organisation_id = :organisation AND attempts < :max ORDER BY last_seen_at, flow_id',
                ['organisation' => $organisationId, 'max' => $maxAttempts],
            ),
        );
    }

    public function resolve(string $organisationId, string $flowId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM electronic_invoicing__unprocessed_flows WHERE organisation_id = :organisation AND flow_id = :flow',
            ['organisation' => $organisationId, 'flow' => $flowId],
        );
    }
}
