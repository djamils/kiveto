<?php

declare(strict_types=1);

namespace App\Context\Prescription\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Prescription\Domain\Service\PrescriptionNumberGeneratorInterface;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionNumber;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Hands out prescription numbers from a row locked for the duration of the
 * write, so two practitioners signing at the same second cannot be given the
 * same one and the sequence keeps no holes.
 */
final readonly class DbalPrescriptionNumberGenerator implements PrescriptionNumberGeneratorInterface
{
    public function __construct(
        private Connection $connection,
        private UuidGeneratorInterface $uuidGenerator,
    ) {
    }

    public function next(ClinicId $clinicId, int $year): PrescriptionNumber
    {
        $clinicBinary = Uuid::fromString($clinicId->toString())->toBinary();

        $this->connection->executeStatement(
            'INSERT IGNORE INTO prescription__number_sequences (id, clinic_id, `year`, `last_value`)
             VALUES (:id, :clinicId, :year, 0)',
            [
                'id'       => Uuid::fromString($this->uuidGenerator->generate())->toBinary(),
                'clinicId' => $clinicBinary,
                'year'     => $year,
            ],
        );

        $current = $this->connection->fetchOne(
            'SELECT `last_value` FROM prescription__number_sequences
             WHERE clinic_id = :clinicId AND `year` = :year FOR UPDATE',
            ['clinicId' => $clinicBinary, 'year' => $year],
        );

        $next = (is_numeric($current) ? (int) $current : 0) + 1;

        $this->connection->executeStatement(
            'UPDATE prescription__number_sequences SET `last_value` = :next
             WHERE clinic_id = :clinicId AND `year` = :year',
            ['next' => $next, 'clinicId' => $clinicBinary, 'year' => $year],
        );

        return PrescriptionNumber::of($year, $next);
    }
}
