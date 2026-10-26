<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Clinic\Settings;

use App\Context\Clinic\Application\Command\Clinic\ChangeClinicBrandColor\ChangeClinicBrandColor;
use App\Context\Clinic\Application\Command\Clinic\ChangeClinicBrandColor\ChangeClinicBrandColorHandler;
use App\Context\Clinic\Application\Command\Clinic\ChangeClinicLogo\ChangeClinicLogo;
use App\Context\Clinic\Application\Command\Clinic\ChangeClinicLogo\ChangeClinicLogoHandler;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicBillingSettings\UpdateClinicBillingSettings;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicBillingSettings\UpdateClinicBillingSettingsHandler;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicLetterhead\UpdateClinicLetterhead;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicLetterhead\UpdateClinicLetterheadHandler;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicOpeningHours\UpdateClinicOpeningHours;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicOpeningHours\UpdateClinicOpeningHoursHandler;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinicHandler;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\LetterheadDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettingsHandler;
use App\Context\Clinic\Application\Service\InvoiceNumberingUniqueness;
use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Exception\InvalidBillingSettingsException;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Context\Clinic\Domain\ValueObject\Letterhead;
use App\Context\Clinic\Domain\ValueObject\OrdinalCouncil;
use App\Context\Clinic\Domain\ValueObject\VatRegime;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The commands and the read the settings page goes through.
 */
final class ClinicSettingsHandlersTest extends TestCase
{
    private const string CLINIC_ID = '018f1b1e-1234-7890-abcd-0123456789ab';

    public function testTheBillingSettingsAreReplacedWhole(): void
    {
        $clinic = self::clinic();

        (new UpdateClinicBillingSettingsHandler($this->repository($clinic), $this->clock(), $this->publisher(), $this->numbering()))(new UpdateClinicBillingSettings(
            clinicId: self::CLINIC_ID,
            streetLine1: '5 rue du Port',
            streetLine2: 'Bât. B',
            postalCode: '13002',
            city: 'Marseille',
            countryCode: 'FR',
            invoiceNumberPrefix: 'fa',
            paymentTermsDays: 15,
            iban: 'FR76 3000 6000 0112 3456 7890 189',
            bic: 'AGRIFRPP',
            invoiceFooterNote: 'Merci.',
            creditNotePrefix: 'av',
            professionalPaymentTermsDays: 45,
            vatRegime: 'SIMPLIFIED',
            vatOnDebits: true,
            acceptedPaymentMethods: ['CASH', 'CARD'],
        ));

        $billing = $clinic->billingSettings();
        self::assertSame('Marseille', $billing->billingAddress?->city);
        self::assertSame('FA', $billing->invoiceNumberPrefix);
        self::assertSame('AV', $billing->creditNotePrefix);
        self::assertSame(45, $billing->professionalPaymentTermsDays);
        self::assertSame(VatRegime::SIMPLIFIED, $billing->vatRegime);
        self::assertTrue($billing->vatOnDebits);
        self::assertSame(['CARD', 'CASH'], $billing->acceptedPaymentMethods);
    }

    public function testBillingWithoutACityOrARegimeKeepsNeither(): void
    {
        $clinic = self::clinic();

        (new UpdateClinicBillingSettingsHandler($this->repository($clinic), $this->clock(), $this->publisher(), $this->numbering()))(new UpdateClinicBillingSettings(
            clinicId: self::CLINIC_ID,
            streetLine1: '5 rue du Port',
            city: '  ',
            countryCode: 'FR',
            vatRegime: '',
        ));

        self::assertNull($clinic->billingSettings()->billingAddress);
        self::assertNull($clinic->billingSettings()->vatRegime);
    }

    public function testAnUnknownVatRegimeIsRefused(): void
    {
        $this->expectException(InvalidBillingSettingsException::class);

        (new UpdateClinicBillingSettingsHandler($this->repository(self::clinic(), saves: false), $this->clock(), $this->publisher(), $this->numbering()))(new UpdateClinicBillingSettings(
            clinicId: self::CLINIC_ID,
            vatRegime: 'WEEKLY',
        ));
    }

    public function testTheWeekAndItsClosuresAreStored(): void
    {
        $clinic = self::clinic();

        (new UpdateClinicOpeningHoursHandler($this->repository($clinic), $this->clock(), $this->publisher()))(new UpdateClinicOpeningHours(
            clinicId: self::CLINIC_ID,
            week: [1 => [['opensAt' => '09:00', 'closesAt' => '12:30'], ['opensAt' => '14:00', 'closesAt' => '19:00']]],
            closures: [['date' => '2026-12-24', 'reason' => 'Noël'], ['date' => '2026-12-25']],
        ));

        self::assertCount(2, $clinic->openingHours()->slotsOn(1));
        self::assertTrue($clinic->openingHours()->isClosedOn(7));
        self::assertCount(2, $clinic->openingHours()->closures);
        self::assertNull($clinic->openingHours()->closures[1]->reason);
    }

    public function testTheBrandColourIsSetThenCleared(): void
    {
        $clinic  = self::clinic();
        $handler = new ChangeClinicBrandColorHandler($this->repository($clinic, times: 2), $this->clock(), $this->publisher());

        $handler(new ChangeClinicBrandColor(self::CLINIC_ID, '#6DBE78'));
        self::assertSame('#6dbe78', $clinic->brandColor()?->toString());

        $handler(new ChangeClinicBrandColor(self::CLINIC_ID, ' '));
        self::assertNull($clinic->brandColor());
    }

    public function testTheLogoIsPointedAt(): void
    {
        $clinic = self::clinic();

        (new ChangeClinicLogoHandler($this->repository($clinic), $this->clock(), $this->publisher()))(new ChangeClinicLogo(self::CLINIC_ID, 'logo.png'));

        self::assertSame('logo.png', $clinic->logoKey());
    }

    public function testTheLetterheadTakesTheCouncilAndTheInsurer(): void
    {
        $clinic = self::clinic();

        (new UpdateClinicLetterheadHandler($this->repository($clinic), $this->clock(), $this->publisher(), $this->numbering()))(new UpdateClinicLetterhead(
            clinicId: self::CLINIC_ID,
            ordinalCouncil: 'paca',
            liabilityInsurer: 'MACSF',
            liabilityPolicyNumber: '4401220788',
        ));

        self::assertSame(OrdinalCouncil::PROVENCE_ALPES_COTE_D_AZUR, $clinic->letterhead()->ordinalCouncil);
        self::assertSame('MACSF', $clinic->letterhead()->liabilityInsurer);
        self::assertSame('4401220788', $clinic->letterhead()->liabilityPolicyNumber);
    }

    /**
     * @return iterable<string, array{0: callable(ClinicRepositoryInterface, ClockInterface, DomainEventPublisher): void}>
     */
    public static function provideAWriteToAnUnknownClinicIsRefusedCases(): iterable
    {
        yield 'billing' => [static fn (ClinicRepositoryInterface $r, ClockInterface $c, DomainEventPublisher $p) => (new UpdateClinicBillingSettingsHandler($r, $c, $p, new InvoiceNumberingUniqueness($r)))(new UpdateClinicBillingSettings(self::CLINIC_ID))];
        yield 'hours' => [static fn (ClinicRepositoryInterface $r, ClockInterface $c, DomainEventPublisher $p) => (new UpdateClinicOpeningHoursHandler($r, $c, $p))(new UpdateClinicOpeningHours(self::CLINIC_ID, [], []))];
        yield 'colour' => [static fn (ClinicRepositoryInterface $r, ClockInterface $c, DomainEventPublisher $p) => (new ChangeClinicBrandColorHandler($r, $c, $p))(new ChangeClinicBrandColor(self::CLINIC_ID, null))];
        yield 'logo' => [static fn (ClinicRepositoryInterface $r, ClockInterface $c, DomainEventPublisher $p) => (new ChangeClinicLogoHandler($r, $c, $p))(new ChangeClinicLogo(self::CLINIC_ID, null))];
    }

    /**
     * @param callable(ClinicRepositoryInterface, ClockInterface, DomainEventPublisher): void $write
     */
    #[DataProvider('provideAWriteToAnUnknownClinicIsRefusedCases')]
    public function testAWriteToAnUnknownClinicIsRefused(callable $write): void
    {
        $repository = $this->createMock(ClinicRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('Clinic with ID "%s" not found.', self::CLINIC_ID));

        $write($repository, $this->clock(), $this->publisher());
    }

    public function testTheSettingsReadHasEveryFieldTheFormsNeed(): void
    {
        $clinic = self::clinic();
        $at     = new \DateTimeImmutable('2026-10-15T10:00:00Z');
        $clinic->updateLetterhead(Letterhead::create(legalName: 'SELARL', ordinalCouncil: 'idf', liabilityInsurer: 'MACSF'), $at);
        (new UpdateClinicBillingSettingsHandler($this->repository($clinic), $this->clock(), $this->publisher(), $this->numbering()))(new UpdateClinicBillingSettings(
            clinicId: self::CLINIC_ID,
            streetLine1: '5 rue du Port',
            city: 'Marseille',
            countryCode: 'FR',
            vatRegime: 'MONTHLY',
            acceptedPaymentMethods: ['CARD'],
        ));
        (new UpdateClinicOpeningHoursHandler($this->repository($clinic), $this->clock(), $this->publisher()))(new UpdateClinicOpeningHours(
            clinicId: self::CLINIC_ID,
            week: [2 => [['opensAt' => '08:00', 'closesAt' => '18:00']]],
            closures: [],
        ));
        $clinic->changeLogo('logo.png', $at);

        $repository = $this->createStub(ClinicRepositoryInterface::class);
        $repository->method('findById')->willReturn($clinic);

        $view = (new GetClinicSettingsHandler($repository))(new GetClinicSettings(self::CLINIC_ID));

        self::assertNotNull($view);
        self::assertSame('Clinique', $view->name);
        self::assertSame('clinique', $view->slug);
        self::assertSame('Europe/Paris', $view->timeZone);
        self::assertSame('SELARL', $view->letterhead['legalName']);
        self::assertSame('idf', $view->letterhead['ordinalCouncil']);
        self::assertSame('MACSF', $view->letterhead['liabilityInsurer']);
        self::assertSame('Marseille', $view->billing['city']);
        self::assertSame('MONTHLY', $view->billing['vatRegime']);
        self::assertSame(['CARD'], $view->billing['acceptedPaymentMethods']);
        self::assertFalse($view->billing['vatOnDebits']);
        self::assertSame('logo.png', $view->logoKey);
        self::assertNull($view->brandColor);
        self::assertTrue($view->isOpenOn(2));
        self::assertFalse($view->isOpenOn(1));
    }

    public function testTheSettingsOfAnUnknownClinicAreNothing(): void
    {
        $repository = $this->createStub(ClinicRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        self::assertNull((new GetClinicSettingsHandler($repository))(new GetClinicSettings(self::CLINIC_ID)));
    }

    public function testTheDocumentsReadTheBrandingAndTheInsurer(): void
    {
        $clinic = self::clinic();
        $clinic->updateLetterhead(Letterhead::create(ordinalCouncil: 'ara', liabilityInsurer: 'MACSF', liabilityPolicyNumber: '4401'), new \DateTimeImmutable());
        $clinic->changeLogo('logo.png', new \DateTimeImmutable());

        $repository = $this->createStub(ClinicRepositoryInterface::class);
        $repository->method('findById')->willReturn($clinic);

        $dto = (new GetClinicHandler($repository))(new GetClinic(self::CLINIC_ID));

        self::assertNotNull($dto);
        self::assertNotNull($dto->letterhead);
        self::assertSame('logo.png', $dto->logoKey);
        self::assertNull($dto->brandColor);
        self::assertSame('Auvergne-Rhône-Alpes', $dto->letterhead->ordinalCouncil);
        self::assertSame('Assurance responsabilité civile professionnelle : MACSF, contrat n° 4401', $dto->letterhead->liabilitySentence());
        self::assertSame('Assurance responsabilité civile professionnelle : MACSF', LetterheadDto::fromDomain(Letterhead::create(liabilityInsurer: 'MACSF'))->liabilitySentence());
        self::assertNull(LetterheadDto::fromDomain(Letterhead::empty())->liabilitySentence());
    }

    private function numbering(): InvoiceNumberingUniqueness
    {
        return new InvoiceNumberingUniqueness($this->createStub(ClinicRepositoryInterface::class));
    }

    private function repository(Clinic $clinic, bool $saves = true, int $times = 1): ClinicRepositoryInterface
    {
        $repository = $this->createMock(ClinicRepositoryInterface::class);
        $repository->method('findById')->willReturn($clinic);
        $repository->expects($saves ? self::exactly($times) : self::never())->method('save')->with($clinic);

        return $repository;
    }

    private function clock(): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-15T10:00:00Z'));

        return $clock;
    }

    private function publisher(): DomainEventPublisher
    {
        return new DomainEventPublisher($this->createStub(EventBusInterface::class));
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
