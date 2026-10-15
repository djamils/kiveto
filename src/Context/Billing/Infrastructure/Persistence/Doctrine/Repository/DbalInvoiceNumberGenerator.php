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
 * Hands out invoice and credit note numbers from a row locked for the duration of the write.
 *
 * Two receptionists cashing up at the same second must not be given the same
 * number, and the sequence must have no holes, so the counter lives in its own
 * table and is read back under `FOR UPDATE` rather than derived from MAX() over
 * the invoices.
 */
final readonly class DbalInvoiceNumberGenerator implements InvoiceNumberGeneratorInterface
{
    private const string SERIES_INVOICE     = 'INVOICE';
    private const string SERIES_CREDIT_NOTE = 'CREDIT_NOTE';

    /** Credit notes print under their own prefix unless the clinic chose one. */
    private const string CREDIT_NOTE_PREFIX = 'AV';

    public function __construct(
        private Connection $connection,
        private UuidGeneratorInterface $uuidGenerator,
        private InvoiceNumberPrefixProviderInterface $prefixes,
    ) {
    }

    public function next(ClinicId $clinicId, int $year): InvoiceNumber
    {
        return InvoiceNumber::of(
            $year,
            $this->advance($clinicId, self::SERIES_INVOICE, $year),
            $this->prefixes->prefixFor($clinicId->toString()),
        );
    }

    public function nextCreditNote(ClinicId $clinicId, int $year): InvoiceNumber
    {
        return InvoiceNumber::of(
            $year,
            $this->advance($clinicId, self::SERIES_CREDIT_NOTE, $year),
            $this->prefixes->creditNotePrefixFor($clinicId->toString()) ?? self::CREDIT_NOTE_PREFIX,
        );
    }

    public function peekNext(ClinicId $clinicId, int $year): InvoiceNumber
    {
        return InvoiceNumber::of(
            $year,
            $this->lastIssued($clinicId, self::SERIES_INVOICE, $year) + 1,
            $this->prefixes->prefixFor($clinicId->toString()),
        );
    }

    public function peekNextCreditNote(ClinicId $clinicId, int $year): InvoiceNumber
    {
        return InvoiceNumber::of(
            $year,
            $this->lastIssued($clinicId, self::SERIES_CREDIT_NOTE, $year) + 1,
            $this->prefixes->creditNotePrefixFor($clinicId->toString()) ?? self::CREDIT_NOTE_PREFIX,
        );
    }

    /** A plain read: nothing is reserved, so nothing needs locking. */
    private function lastIssued(ClinicId $clinicId, string $series, int $year): int
    {
        $current = $this->connection->fetchOne(
            'SELECT `last_value` FROM billing__number_sequences
             WHERE clinic_id = :clinicId AND series = :series AND `year` = :year',
            ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary(), 'series' => $series, 'year' => $year],
        );

        return is_numeric($current) ? (int) $current : 0;
    }

    private function advance(ClinicId $clinicId, string $series, int $year): int
    {
        $clinicBinary = Uuid::fromString($clinicId->toString())->toBinary();
        $key          = ['clinicId' => $clinicBinary, 'series' => $series, 'year' => $year];

        $this->connection->executeStatement(
            'INSERT IGNORE INTO billing__number_sequences (id, clinic_id, series, `year`, `last_value`)
             VALUES (:id, :clinicId, :series, :year, 0)',
            ['id' => Uuid::fromString($this->uuidGenerator->generate())->toBinary()] + $key,
        );

        $current = $this->connection->fetchOne(
            'SELECT `last_value` FROM billing__number_sequences
             WHERE clinic_id = :clinicId AND series = :series AND `year` = :year FOR UPDATE',
            $key,
        );

        $next = (is_numeric($current) ? (int) $current : 0) + 1;

        $this->connection->executeStatement(
            'UPDATE billing__number_sequences SET `last_value` = :next
             WHERE clinic_id = :clinicId AND series = :series AND `year` = :year',
            ['next' => $next] + $key,
        );

        return $next;
    }
}
