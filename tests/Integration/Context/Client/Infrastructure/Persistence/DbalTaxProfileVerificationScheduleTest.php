<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Infrastructure\Persistence;

use App\Context\Client\Application\Verification\TaxProfileVerificationScheduleInterface;
use App\Context\Client\Domain\TaxProfile\CompanyCheckOutcome;
use App\Context\Client\Domain\TaxProfile\VatCheckOutcome;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * The nightly finder, across clinics. « now » is 2026-10-19 02:17.
 */
final class DbalTaxProfileVerificationScheduleTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_A = '01960000-0000-7000-8000-0000000b01c1';
    private const string CLINIC_B = '01960000-0000-7000-8000-0000000b01c2';

    private const string NEVER       = '01960000-0000-7000-8000-0000000b0101';
    private const string REGISTRY_KO = '01960000-0000-7000-8000-0000000b0102';
    private const string VIES_KO     = '01960000-0000-7000-8000-0000000b0103';
    private const string OLD         = '01960000-0000-7000-8000-0000000b0104';
    private const string FRESH       = '01960000-0000-7000-8000-0000000b0105';
    private const string INDIVIDUAL  = '01960000-0000-7000-8000-0000000b0106';
    private const string ARCHIVED    = '01960000-0000-7000-8000-0000000b0107';
    private const string NO_SIREN    = '01960000-0000-7000-8000-0000000b0108';
    private const string REQUESTED   = '01960000-0000-7000-8000-0000000b0109';
    private const string LONG_AGO    = '01960000-0000-7000-8000-0000000b0110';
    private const string OTHER       = '01960000-0000-7000-8000-0000000b0111';

    private const string NOW = '2026-10-19 02:17:00';

    public function testTheProfilesDueForAVerificationAreFoundNeverCheckedFirst(): void
    {
        $this->seed();

        self::assertSame([
            [self::CLINIC_A, self::NEVER],
            [self::CLINIC_A, self::LONG_AGO],
            [self::CLINIC_B, self::OTHER],
            [self::CLINIC_A, self::OLD],
            [self::CLINIC_A, self::REGISTRY_KO],
            [self::CLINIC_A, self::VIES_KO],
        ], $this->stale());
    }

    public function testMarkingSetsTheRequestBumpsTheVersionAndSkipsARequestMadeMeanwhile(): void
    {
        $this->seed();
        $before = $this->version(self::NEVER);

        // REQUESTED was requested 2 hours ago: not marked again.
        $marked = $this->schedule()->markRequested([self::NEVER, self::OTHER, self::REQUESTED], new \DateTimeImmutable(self::NOW), new \DateTimeImmutable('2026-10-18 02:17:00'));

        self::assertSame([self::NEVER, self::OTHER], $marked);
        self::assertSame(self::NOW, $this->requestedAt(self::NEVER));
        self::assertSame($before + 1, $this->version(self::NEVER));
        self::assertSame('2026-10-19 00:17:00', $this->requestedAt(self::REQUESTED));
        self::assertSame([], array_values(array_filter($this->stale(), static fn (array $row): bool => \in_array($row[1], [self::NEVER, self::OTHER], true))));
    }

    public function testTheWindowsIncludeTheirBoundary(): void
    {
        $pro = static fn (string $id): ClientEntityFactory => ClientEntityFactory::new()->withId($id)->withClinicId(self::CLINIC_A)->withName('Pro', $id)
            ->withTaxProfile(ClientTaxStatus::FRENCH_BUSINESS, 'Structure', '523456788')->active()->since('2026-01-01')
        ;

        // Requested exactly 24 hours ago (last night's run, never answered), checked exactly 30 days ago.
        $pro(self::LONG_AGO)->withTaxVerification(CompanyCheckOutcome::UNCHECKED, null, null, VatCheckOutcome::UNCHECKED, null, '2026-10-18 02:17:00')->create();
        $pro(self::OLD)->withTaxVerification(CompanyCheckOutcome::ACTIVE, 'X', '2026-09-19 02:17:00', VatCheckOutcome::VALID, '2026-09-19 02:17:00')->create();

        self::assertSame([[self::CLINIC_A, self::LONG_AGO], [self::CLINIC_A, self::OLD]], $this->stale());
        self::assertSame([self::LONG_AGO, self::OLD], $this->schedule()->markRequested([self::LONG_AGO, self::OLD], new \DateTimeImmutable(self::NOW), new \DateTimeImmutable('2026-10-18 02:17:00')));
    }

    public function testARequestMadeInTheSameSecondIsNotSentTwice(): void
    {
        // « Revérifier » committed at the very second of the nightly run.
        ClientEntityFactory::new()->withId(self::NEVER)->withClinicId(self::CLINIC_A)->withName('Never', 'Checked')
            ->withTaxProfile(ClientTaxStatus::FRENCH_BUSINESS, 'Jamais', '523456788')
            ->withTaxVerification(CompanyCheckOutcome::UNCHECKED, null, null, VatCheckOutcome::UNCHECKED, null, self::NOW)
            ->active()->since('2026-01-01')->create()
        ;

        self::assertSame([], $this->schedule()->markRequested([self::NEVER], new \DateTimeImmutable(self::NOW), new \DateTimeImmutable('2026-10-18 02:17:00')));
    }

    public function testMarkingWorksInChunks(): void
    {
        $ids = [self::NEVER];

        for ($i = 0; $i < 600; ++$i) {
            $ids[] = \sprintf('01960000-0000-7000-8000-%012x', 0xC000000 + $i);
        }

        ClientEntityFactory::new()->withId(self::NEVER)->withClinicId(self::CLINIC_A)->withName('Never', 'Checked')
            ->withTaxProfile(ClientTaxStatus::FRENCH_BUSINESS, 'Jamais', '523456788')->active()->since('2026-01-01')->create()
        ;

        $marked = $this->schedule()->markRequested($ids, new \DateTimeImmutable(self::NOW), new \DateTimeImmutable('2026-10-18 02:17:00'));

        self::assertSame([self::NEVER], $marked);
    }

    /**
     * @return list<array{string, string}>
     */
    private function stale(): array
    {
        return array_map(
            static fn (array $row): array => [$row['clinicId'], $row['clientId']],
            $this->schedule()->findStale(new \DateTimeImmutable('2026-09-19 02:17:00'), new \DateTimeImmutable('2026-10-18 02:17:00')),
        );
    }

    private function seed(): void
    {
        $pro = static fn (string $id, string $clinic = self::CLINIC_A): ClientEntityFactory => ClientEntityFactory::new()
            ->withId($id)->withClinicId($clinic)->withName('Pro', $id)
            ->withTaxProfile(ClientTaxStatus::FRENCH_BUSINESS, 'Structure ' . $id, '523456788')
            ->active()->since('2026-01-01')
        ;

        $pro(self::NEVER)->create();
        $pro(self::REGISTRY_KO)->withTaxVerification(CompanyCheckOutcome::UNAVAILABLE, null, '2026-10-18 01:00:00', VatCheckOutcome::VALID, '2026-10-18 01:00:00')->create();
        $pro(self::VIES_KO)->withTaxVerification(CompanyCheckOutcome::ACTIVE, 'X', '2026-10-18 01:00:01', VatCheckOutcome::UNAVAILABLE, '2026-10-18 01:00:01')->create();
        $pro(self::OLD)->withTaxVerification(CompanyCheckOutcome::ACTIVE, 'X', '2026-09-01 10:00:00', VatCheckOutcome::VALID, '2026-09-01 10:00:00')->create();
        $pro(self::FRESH)->withTaxVerification(CompanyCheckOutcome::CONFIDENTIAL, null, '2026-10-10 10:00:00', VatCheckOutcome::NOT_APPLICABLE, '2026-10-10 10:00:00')->create();
        $pro(self::ARCHIVED)->archived()->create();
        $pro(self::REQUESTED)->withTaxVerification(CompanyCheckOutcome::UNCHECKED, null, null, VatCheckOutcome::UNCHECKED, null, '2026-10-19 00:17:00')->create();
        // Requested more than 24 hours ago and never answered (a lost message): found again.
        $pro(self::LONG_AGO)->withTaxVerification(CompanyCheckOutcome::UNCHECKED, null, null, VatCheckOutcome::UNCHECKED, null, '2026-10-17 02:17:00')->create();
        $pro(self::OTHER, self::CLINIC_B)->create();

        ClientEntityFactory::new()->withId(self::INDIVIDUAL)->withClinicId(self::CLINIC_A)->withName('Inès', 'Perrin')
            ->withTaxProfile(ClientTaxStatus::INDIVIDUAL, 'Refuge des Collines')->active()->since('2026-01-01')->create()
        ;
        ClientEntityFactory::new()->withId(self::NO_SIREN)->withClinicId(self::CLINIC_A)->withName('Alice', 'Martin')
            ->withTaxProfile(ClientTaxStatus::FRENCH_BUSINESS, 'Sans SIREN', null)->active()->since('2026-01-01')->create()
        ;
    }

    private function schedule(): TaxProfileVerificationScheduleInterface
    {
        $schedule = self::getContainer()->get(TaxProfileVerificationScheduleInterface::class);
        \assert($schedule instanceof TaxProfileVerificationScheduleInterface);

        return $schedule;
    }

    private function requestedAt(string $id): mixed
    {
        return $this->connection()->fetchOne('SELECT tax_verification_requested_at FROM client__clients WHERE id = UUID_TO_BIN(:id)', ['id' => $id]);
    }

    private function version(string $id): int
    {
        $version = $this->connection()->fetchOne('SELECT version FROM client__clients WHERE id = UUID_TO_BIN(:id)', ['id' => $id]);

        return (int) (\is_scalar($version) ? $version : 0);
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
