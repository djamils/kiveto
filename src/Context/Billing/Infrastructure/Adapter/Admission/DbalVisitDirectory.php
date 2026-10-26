<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\Admission;

use App\Context\Billing\Application\Port\VisitDirectoryInterface;
use App\Context\Billing\Application\Port\VisitSummary;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * The visit an invoice charges: admission → patient → animal. The chain
 * crosses three contexts none of which exposes it as one query, so it is
 * read here as one projection (like DbalPayerDirectory). The animal's own
 * row is read, not the search index rebuilt after the fact.
 */
final readonly class DbalVisitDirectory implements VisitDirectoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function visitOf(string $clinicId, string $admissionId): ?VisitSummary
    {
        $row = $this->connection->fetchAssociative(
            'SELECT a.opened_at, an.name AS animal_name
             FROM admission__admissions a
             INNER JOIN patient__patients p ON p.id = a.patient_id
             LEFT JOIN animal__animals an ON an.id = p.animal_link_id
             WHERE a.id = :admissionId AND a.clinic_id = :clinicId',
            [
                'admissionId' => Uuid::fromString($admissionId)->toBinary(),
                'clinicId'    => Uuid::fromString($clinicId)->toBinary(),
            ],
        );

        if (false === $row) {
            return null;
        }

        $name = RowAccessor::nullableString($row, 'animal_name');

        return new VisitSummary(
            null !== $name && '' !== mb_trim($name) ? mb_trim($name) : null,
            new \DateTimeImmutable(RowAccessor::string($row, 'opened_at'), new \DateTimeZone('UTC')),
        );
    }
}
