<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain;

use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Event\ClinicBillingSettingsUpdated;
use App\Context\Clinic\Domain\Event\ClinicBrandingChanged;
use App\Context\Clinic\Domain\Event\ClinicOpeningHoursUpdated;
use App\Context\Clinic\Domain\Exception\InvalidBillingSettingsException;
use App\Context\Clinic\Domain\Staff\ValueObject\HexColor;
use App\Context\Clinic\Domain\ValueObject\BillingSettings;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Context\Clinic\Domain\ValueObject\Letterhead;
use App\Context\Clinic\Domain\ValueObject\OpeningHours;
use App\Context\Clinic\Domain\ValueObject\OrdinalCouncil;
use App\Context\Clinic\Domain\ValueObject\VatRegime;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use PHPUnit\Framework\TestCase;

/**
 * What the settings page writes into the clinic: the regional council and the
 * liability insurer on the letterhead, the new billing choices, the colour and
 * the logo.
 */
final class ClinicSettingsDomainTest extends TestCase
{
    private const string CLINIC_ID = '018f1b1e-1234-7890-abcd-0123456789ab';

    public function testTheLetterheadKeepsTheCouncilAndTheInsurer(): void
    {
        $letterhead = Letterhead::create(ordinalCouncil: ' idf ', liabilityInsurer: ' MACSF ', liabilityPolicyNumber: 'mac 4401');

        self::assertSame(OrdinalCouncil::ILE_DE_FRANCE, $letterhead->ordinalCouncil);
        self::assertSame('MACSF', $letterhead->liabilityInsurer);
        self::assertSame('MAC 4401', $letterhead->liabilityPolicyNumber);
        self::assertFalse($letterhead->isEmpty());
        self::assertTrue($letterhead->equals(Letterhead::create(ordinalCouncil: 'idf', liabilityInsurer: 'MACSF', liabilityPolicyNumber: 'MAC 4401')));
        self::assertFalse($letterhead->equals(Letterhead::create(ordinalCouncil: 'ara', liabilityInsurer: 'MACSF', liabilityPolicyNumber: 'MAC 4401')));
        self::assertTrue(Letterhead::create(ordinalCouncil: '  ')->isEmpty());
    }

    public function testAnUnknownCouncilIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown regional council of the Order: "xyz".');

        Letterhead::create(ordinalCouncil: 'xyz');
    }

    public function testEveryCouncilHasAName(): void
    {
        $labels = array_map(static fn (OrdinalCouncil $council): string => $council->label(), OrdinalCouncil::cases());

        self::assertCount(14, array_unique($labels));
        self::assertSame('Provence-Alpes-Côte d’Azur', OrdinalCouncil::PROVENCE_ALPES_COTE_D_AZUR->label());
    }

    public function testTheNewBillingChoicesAreKept(): void
    {
        $settings = BillingSettings::create(
            creditNotePrefix: 'av',
            professionalPaymentTermsDays: 45,
            vatRegime: VatRegime::QUARTERLY,
            vatOnDebits: true,
            acceptedPaymentMethods: ['TRANSFER', 'CARD', 'CARD'],
        );

        self::assertSame('AV', $settings->creditNotePrefix);
        self::assertSame(45, $settings->professionalPaymentTermsDays);
        self::assertSame(VatRegime::QUARTERLY, $settings->vatRegime);
        self::assertTrue($settings->vatOnDebits);
        // Known order, each once: the same choice compares equal however it was ticked.
        self::assertSame(['CARD', 'TRANSFER'], $settings->acceptedPaymentMethods);
        self::assertTrue($settings->accepts('CARD'));
        self::assertFalse($settings->accepts('CHECK'));
        self::assertTrue(BillingSettings::create()->accepts('CHECK'));
        self::assertFalse($settings->isEmpty());
        self::assertTrue($settings->equals(BillingSettings::create(
            creditNotePrefix: 'AV',
            professionalPaymentTermsDays: 45,
            vatRegime: VatRegime::QUARTERLY,
            vatOnDebits: true,
            acceptedPaymentMethods: ['CARD', 'TRANSFER'],
        )));
        self::assertFalse($settings->equals(BillingSettings::create(creditNotePrefix: 'AV')));
        self::assertTrue(BillingSettings::create(creditNotePrefix: ' ')->isEmpty());
    }

    public function testAMalformedCreditNotePrefixIsRefused(): void
    {
        $this->expectException(InvalidBillingSettingsException::class);

        BillingSettings::create(creditNotePrefix: 'A V');
    }

    public function testProfessionalTermsStayWithinAYear(): void
    {
        $this->expectException(InvalidBillingSettingsException::class);
        $this->expectExceptionMessage('Payment terms of "400" days are outside what an invoice can carry.');

        BillingSettings::create(professionalPaymentTermsDays: 400);
    }

    public function testAnUnknownPaymentMethodIsRefused(): void
    {
        $this->expectException(InvalidBillingSettingsException::class);
        $this->expectExceptionMessage('Payment method "BITCOIN" is not one a clinic can take.');

        BillingSettings::create(acceptedPaymentMethods: ['CARD', 'BITCOIN']);
    }

    public function testAPaymentMethodThatIsNotTextIsRefused(): void
    {
        $this->expectException(InvalidBillingSettingsException::class);
        $this->expectExceptionMessage('Payment method "int" is not one a clinic can take.');

        BillingSettings::create(acceptedPaymentMethods: [3]);
    }

    public function testAnUnknownVatRegimeIsNamed(): void
    {
        self::assertSame('VAT regime "WEEKLY" is not one a clinic can file under.', InvalidBillingSettingsException::unknownVatRegime('WEEKLY')->getMessage());
    }

    public function testTheBrandColourIsChangedOnceAndCanBeCleared(): void
    {
        $clinic = self::clinic();
        self::assertNotEmpty($clinic->pullDomainEvents());

        $clinic->changeBrandColor(HexColor::fromString('#4F86C6'), new \DateTimeImmutable('2026-10-15T10:00:00Z'));
        $clinic->changeBrandColor(HexColor::fromString('#4f86c6'), new \DateTimeImmutable('2026-10-15T11:00:00Z'));

        self::assertSame('#4f86c6', $clinic->brandColor()?->toString());
        self::assertSame('2026-10-15T10:00:00+00:00', $clinic->updatedAt()->format('c'));

        $events = $clinic->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ClinicBrandingChanged::class, $events[0]);
        self::assertSame(['clinicId' => self::CLINIC_ID, 'brandColor' => '#4f86c6', 'hasLogo' => false], $events[0]->payload());
        self::assertSame(self::CLINIC_ID, $events[0]->aggregateId());

        $clinic->changeBrandColor(null, new \DateTimeImmutable('2026-10-15T12:00:00Z'));
        self::assertNull($clinic->brandColor());
    }

    public function testTheLogoIsChangedOnceAndCanBeRemoved(): void
    {
        $clinic = self::clinic();
        self::assertNotEmpty($clinic->pullDomainEvents());

        $clinic->changeLogo(' 2026/10/15/clinic-logos/a.png ', new \DateTimeImmutable('2026-10-15T10:00:00Z'));
        $clinic->changeLogo('2026/10/15/clinic-logos/a.png', new \DateTimeImmutable('2026-10-15T11:00:00Z'));

        self::assertSame('2026/10/15/clinic-logos/a.png', $clinic->logoKey());
        $events = $clinic->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertTrue($events[0]->payload()['hasLogo']);

        $clinic->changeLogo('  ', new \DateTimeImmutable('2026-10-15T12:00:00Z'));
        self::assertNull($clinic->logoKey());
    }

    public function testReconstitutionKeepsTheBranding(): void
    {
        $clinic = Clinic::reconstitute(
            id: ClinicId::fromString(self::CLINIC_ID),
            name: 'Clinique',
            slug: ClinicSlug::fromString('clinique'),
            timeZone: TimeZone::fromString('Europe/Paris'),
            locale: Locale::fromString('fr-FR'),
            countryCode: CountryCode::fromString('FR'),
            currencyCode: CurrencyCode::fromString('EUR'),
            status: \App\Context\Clinic\Domain\ValueObject\ClinicStatus::ACTIVE,
            createdAt: new \DateTimeImmutable('2026-01-01T10:00:00Z'),
            updatedAt: new \DateTimeImmutable('2026-01-01T10:00:00Z'),
            brandColor: HexColor::fromString('#e07a5f'),
            logoKey: 'logo.svg',
        );

        self::assertSame('#e07a5f', $clinic->brandColor()?->toString());
        self::assertSame('logo.svg', $clinic->logoKey());
    }

    public function testSavingTheSameHoursOrBillingTwiceRecordsNothing(): void
    {
        $clinic = self::clinic();
        self::assertNotEmpty($clinic->pullDomainEvents());
        $at = new \DateTimeImmutable('2026-10-15T10:00:00Z');

        $clinic->updateOpeningHours(OpeningHours::empty(), $at);
        $clinic->updateBillingSettings(BillingSettings::empty(), $at);
        $clinic->updateLetterhead(Letterhead::empty(), $at);

        self::assertSame([], $clinic->pullDomainEvents());
    }

    public function testTheHoursAndBillingEventsSayWhetherTheyWereCleared(): void
    {
        $hours   = new ClinicOpeningHoursUpdated(self::CLINIC_ID, false);
        $billing = new ClinicBillingSettingsUpdated(self::CLINIC_ID, true);

        self::assertSame(self::CLINIC_ID, $hours->aggregateId());
        self::assertSame(['clinicId' => self::CLINIC_ID, 'isEmpty' => false], $hours->payload());
        self::assertSame(self::CLINIC_ID, $billing->aggregateId());
        self::assertSame(['clinicId' => self::CLINIC_ID, 'isEmpty' => true], $billing->payload());
    }

    private static function clinic(): Clinic
    {
        return Clinic::create(
            ClinicId::fromString(self::CLINIC_ID),
            'Clinique',
            ClinicSlug::fromString('clinique'),
            TimeZone::fromString('Europe/Paris'),
            Locale::fromString('fr-FR'),
            CountryCode::fromString('FR'),
            CurrencyCode::fromString('EUR'),
            new \DateTimeImmutable('2026-01-01T10:00:00Z'),
        );
    }
}
