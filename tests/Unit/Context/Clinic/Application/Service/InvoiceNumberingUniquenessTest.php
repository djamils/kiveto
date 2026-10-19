<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Service;

use App\Context\Clinic\Application\Service\InvoiceNumberingUniqueness;
use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Exception\InvoicePrefixAlreadyUsedException;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\BillingSettings;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\Clinic\Domain\ValueObject\Letterhead;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Domain\ValueObject\Siren;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvoiceNumberingUniqueness::class)]
#[CoversClass(InvoicePrefixAlreadyUsedException::class)]
final class InvoiceNumberingUniquenessTest extends TestCase
{
    private const string LYON_ID   = '01960000-0000-7000-8000-000000000101';
    private const string CASSIS_ID = '01960000-0000-7000-8000-000000000102';

    public function testCassisMayNotTakeThePrefixOfLyon(): void
    {
        $cassis = self::clinic(self::CASSIS_ID, '90112233300023', 'CAS', 'AVCAS');

        try {
            $this->uniqueness([self::clinic(self::LYON_ID, '90112233300015', 'LYO', 'AVLYO')])
                ->assertChangeKeepsSeriesDistinct($cassis, $cassis->letterhead(), BillingSettings::create(invoiceNumberPrefix: 'LYO', creditNotePrefix: 'AVCAS'))
            ;
            self::fail('The prefix of Lyon should have been refused.');
        } catch (InvoicePrefixAlreadyUsedException $exception) {
            self::assertSame('LYO', $exception->prefix);
            self::assertSame(InvoicePrefixAlreadyUsedException::SERIES_INVOICE, $exception->series);
            self::assertSame('Number series "LYO" is already used by another establishment of the same company.', $exception->getMessage());
        }
    }

    public function testACreditNotePrefixMayNotBeAnInvoicePrefixOfTheCompany(): void
    {
        $cassis = self::clinic(self::CASSIS_ID, '90112233300023', 'CAS', 'AVCAS');

        try {
            $this->uniqueness([self::clinic(self::LYON_ID, '90112233300015', 'LYO', 'AVLYO')])
                ->assertChangeKeepsSeriesDistinct($cassis, $cassis->letterhead(), BillingSettings::create(invoiceNumberPrefix: 'CAS', creditNotePrefix: 'LYO'))
            ;
            self::fail('The prefix of Lyon should have been refused.');
        } catch (InvoicePrefixAlreadyUsedException $exception) {
            self::assertSame(InvoicePrefixAlreadyUsedException::SERIES_CREDIT_NOTE, $exception->series);
        }
    }

    public function testTheDefaultPrefixesCount(): void
    {
        $lyon   = self::clinic(self::LYON_ID, '90112233300015', null, null);
        $cassis = self::clinic(self::CASSIS_ID, '90112233300023', 'CAS', 'AVCAS');

        self::assertFalse($this->uniqueness([$lyon])->isUnique($cassis->id(), $cassis->letterhead(), BillingSettings::empty()));
        self::assertTrue($this->uniqueness([$lyon])->isUnique($cassis->id(), $cassis->letterhead(), $cassis->billingSettings()));
    }

    public function testAClinicWithoutAValidSirenIsAlwaysUnique(): void
    {
        $repository = $this->createMock(ClinicRepositoryInterface::class);
        $repository->expects(self::never())->method('findOthersWithSiren');

        $clinic = self::clinic(self::CASSIS_ID, null, null, null);

        self::assertTrue((new InvoiceNumberingUniqueness($repository))->isUnique($clinic->id(), Letterhead::reconstitute(siret: '12345678901234'), BillingSettings::empty()));
    }

    public function testAClinicWhoseCompanyAndPrefixesStayAsTheyAreMayStillSave(): void
    {
        // Older data: both establishments left on the default prefixes.
        $cassis     = self::clinic(self::CASSIS_ID, '90112233300023', null, null);
        $repository = $this->createMock(ClinicRepositoryInterface::class);
        $repository->expects(self::never())->method('findOthersWithSiren');

        (new InvoiceNumberingUniqueness($repository))->assertChangeKeepsSeriesDistinct(
            $cassis,
            Letterhead::create(legalName: 'Cassis', siret: '90112233300023'),
            BillingSettings::create(iban: 'FR7630006000011234567890189'),
        );

        $this->addToAssertionCount(1);
    }

    public function testDistinctPrefixesAreAccepted(): void
    {
        $cassis = self::clinic(self::CASSIS_ID, '90112233300023', 'CAS', 'AVCAS');

        $this->uniqueness([self::clinic(self::LYON_ID, '90112233300015', 'LYO', 'AVLYO')])
            ->assertChangeKeepsSeriesDistinct($cassis, $cassis->letterhead(), BillingSettings::create(invoiceNumberPrefix: 'CASSIS', creditNotePrefix: 'AVCASSIS'))
        ;

        $this->addToAssertionCount(1);
    }

    /**
     * @param list<Clinic> $others
     */
    private function uniqueness(array $others): InvoiceNumberingUniqueness
    {
        $repository = $this->createMock(ClinicRepositoryInterface::class);
        $repository->expects(self::once())->method('findOthersWithSiren')
            ->with(
                self::callback(static fn (Siren $siren): bool => '901122333' === $siren->value()),
                self::callback(static fn (ClinicId $id): bool => self::CASSIS_ID === $id->toString()),
            )
            ->willReturn($others)
        ;

        return new InvoiceNumberingUniqueness($repository);
    }

    private static function clinic(string $id, ?string $siret, ?string $invoicePrefix, ?string $creditNotePrefix): Clinic
    {
        return Clinic::reconstitute(
            id: ClinicId::fromString($id),
            name: 'Clinique',
            slug: ClinicSlug::fromString('clinique-' . substr($id, -3)),
            timeZone: TimeZone::fromString('Europe/Paris'),
            locale: Locale::fromString('fr-FR'),
            countryCode: CountryCode::fromString('FR'),
            currencyCode: CurrencyCode::fromString('EUR'),
            status: ClinicStatus::ACTIVE,
            createdAt: new \DateTimeImmutable('2026-01-01T10:00:00Z'),
            updatedAt: new \DateTimeImmutable('2026-01-01T10:00:00Z'),
            letterhead: Letterhead::create(siret: $siret),
            billingSettings: BillingSettings::create(invoiceNumberPrefix: $invoicePrefix, creditNotePrefix: $creditNotePrefix),
        );
    }
}
