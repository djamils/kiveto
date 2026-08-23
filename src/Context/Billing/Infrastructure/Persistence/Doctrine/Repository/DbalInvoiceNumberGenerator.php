<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Billing\Application\Port\InvoiceNumberPrefixProviderInterface;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Hands out invoice numbers from a row locked for the duration of the write.
 *
 * Two receptionists cashing up at the same second must not be given the same
 * number, and the sequence must have no holes, so the counter lives in its own
 * table and is read back under `FOR UPDATE` rather than derived from MAX() over
 * the invoices.
 */
final readonly class DbalInvoiceNumberGenerator implements InvoiceNumberGeneratorInterface
{
    public function __construct(
        private Connection $connection,
        private UuidGeneratorInterface $uuidGenerator,
        private InvoiceNumberPrefixProviderInterface $prefixes,
    ) {
    }

    public function next(ClinicId $clinicId, int $year): InvoiceNumber
    {
        $clinicBinary = Uuid::fromString($clinicId->toString())->toBinary();

        $this->connection->executeStatement(
            'INSERT IGNORE INTO billing__number_sequences (id, clinic_id, `year`, `last_value`)
             VALUES (:id, :clinicId, :year, 0)',
            [
                'id'       => Uuid::fromString($this->uuidGenerator->generate())->toBinary(),
                'clinicId' => $clinicBinary,
                'year'     => $year,
            ],
        );

        $current = $this->connection->fetchOne(
            'SELECT `last_value` FROM billing__number_sequences
             WHERE clinic_id = :clinicId AND `year` = :year FOR UPDATE',
            ['clinicId' => $clinicBinary, 'year' => $year],
        );

        $next = (is_numeric($current) ? (int) $current : 0) + 1;

        $this->connection->executeStatement(
            'UPDATE billing__number_sequences SET `last_value` = :next
             WHERE clinic_id = :clinicId AND `year` = :year',
            ['next' => $next, 'clinicId' => $clinicBinary, 'year' => $year],
        );

        return InvoiceNumber::of($year, $next, $this->prefixes->prefixFor($clinicId->toString()));
    }
}
