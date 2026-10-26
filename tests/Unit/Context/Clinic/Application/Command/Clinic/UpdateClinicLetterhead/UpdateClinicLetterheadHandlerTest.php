<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Clinic\UpdateClinicLetterhead;

use App\Context\Clinic\Application\Command\Clinic\UpdateClinicLetterhead\UpdateClinicLetterhead;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicLetterhead\UpdateClinicLetterheadHandler;
use App\Context\Clinic\Application\Service\InvoiceNumberingUniqueness;
use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Event\ClinicLetterheadUpdated;
use App\Context\Clinic\Domain\Exception\InvalidLetterheadException;
use App\Context\Clinic\Domain\Exception\InvoicePrefixAlreadyUsedException;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Context\Clinic\Domain\ValueObject\Letterhead;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateClinicLetterheadHandler::class)]
#[CoversClass(UpdateClinicLetterhead::class)]
#[CoversClass(ClinicLetterheadUpdated::class)]
final class UpdateClinicLetterheadHandlerTest extends TestCase
{
    private const string CLINIC_ID = '018f1b1e-1234-7890-abcd-0123456789ab';

    public function testTheLetterheadIsStoredAndTheChangeIsPublished(): void
    {
        $clinic = self::clinic();

        $handler = $this->handler($clinic, expectsPublish: true);

        $handler(new UpdateClinicLetterhead(
            clinicId: self::CLINIC_ID,
            legalName: 'Clinique Vétérinaire de Paris',
            legalForm: 'SELARL',
            siret: '812 456 903 00028',
            vatNumber: 'FR20812456903',
            rcsCity: 'Paris',
            rcsNumber: '812 456 903',
            apeCode: '7500Z',
            ordinalNumber: '75-1284',
            streetLine1: '18 rue des Abbesses',
            postalCode: '75018',
            city: 'Paris',
            countryCode: 'FR',
            phone: '+33142551890',
            email: 'contact@vet-paris.fr',
            website: 'www.vet-paris.fr',
        ));

        $letterhead = $clinic->letterhead();

        self::assertSame('Clinique Vétérinaire de Paris', $letterhead->legalName);
        self::assertSame('81245690300028', $letterhead->siret);
        self::assertSame('FR20812456903', $letterhead->vatNumber);
        $address = $letterhead->address;
        self::assertNotNull($address);
        self::assertSame('18 rue des Abbesses', $address->streetLine1);
        self::assertSame('75018', $address->postalCode);
    }

    public function testAVatNumberThatDoesNotMatchTheSiretIsRefused(): void
    {
        $repository = $this->createMock(ClinicRepositoryInterface::class);
        $repository->method('findById')->willReturn(self::clinic());
        $repository->expects(self::never())->method('save');

        $handler = new UpdateClinicLetterheadHandler(
            $repository,
            $this->createStub(ClockInterface::class),
            new DomainEventPublisher($this->createStub(EventBusInterface::class)),
            new InvoiceNumberingUniqueness($repository),
        );

        try {
            $handler(new UpdateClinicLetterhead(clinicId: self::CLINIC_ID, siret: '81245690300028', vatNumber: 'FR00901122333'));
            self::fail('The VAT number of another company should have been refused.');
        } catch (InvalidLetterheadException $exception) {
            self::assertSame(InvalidLetterheadException::FIELD_VAT_NUMBER, $exception->field);
            self::assertSame(InvalidLetterheadException::REASON_SIREN_MISMATCH, $exception->reason);
        }
    }

    public function testASiretThatMakesTwoSeriesOfTheCompanyCollideIsRefused(): void
    {
        $lyon = Clinic::create(
            ClinicId::fromString('01960000-0000-7000-8000-000000000101'),
            'Lyon',
            ClinicSlug::fromString('lyon'),
            TimeZone::fromString('Europe/Paris'),
            Locale::fromString('fr-FR'),
            CountryCode::fromString('FR'),
            CurrencyCode::fromString('EUR'),
            new \DateTimeImmutable('2026-01-01T10:00:00Z'),
        );
        $lyon->updateLetterhead(Letterhead::create(siret: '90112233300015'), new \DateTimeImmutable('2026-01-01T10:00:00Z'));

        $repository = $this->createMock(ClinicRepositoryInterface::class);
        $repository->method('findById')->willReturn(self::clinic());
        $repository->method('findOthersWithSiren')->willReturn([$lyon]);
        $repository->expects(self::never())->method('save');

        $handler = new UpdateClinicLetterheadHandler(
            $repository,
            $this->createStub(ClockInterface::class),
            new DomainEventPublisher($this->createStub(EventBusInterface::class)),
            new InvoiceNumberingUniqueness($repository),
        );

        // Both clinics number under the default « FAC »: joining the company would collide.
        $this->expectException(InvoicePrefixAlreadyUsedException::class);

        $handler(new UpdateClinicLetterhead(clinicId: self::CLINIC_ID, siret: '90112233300023'));
    }

    public function testAnAddressWithoutACityIsNoAddress(): void
    {
        $clinic  = self::clinic();
        $handler = $this->handler($clinic, expectsPublish: true);

        $handler(new UpdateClinicLetterhead(
            clinicId: self::CLINIC_ID,
            legalName: 'Clinique',
            streetLine1: '18 rue des Abbesses',
            city: '   ',
            countryCode: 'FR',
        ));

        self::assertNull($clinic->letterhead()->address);
        self::assertSame('Clinique', $clinic->letterhead()->legalName);
    }

    public function testWritingTheSameLetterheadTwiceRecordsNothing(): void
    {
        $clinic  = self::clinic();
        $command = new UpdateClinicLetterhead(clinicId: self::CLINIC_ID, legalName: 'Clinique');

        $this->handler($clinic, expectsPublish: true)($command);

        // The second write changes nothing, so the aggregate records no event —
        // the event bus mock is what asserts it.
        $this->handler($clinic, expectsPublish: false)($command);

        self::assertSame('Clinique', $clinic->letterhead()->legalName);
        self::assertSame([], $clinic->pullDomainEvents());
    }

    public function testAnUnknownClinicIsRefused(): void
    {
        $repository = $this->createMock(ClinicRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-22T10:00:00Z'));

        $handler = new UpdateClinicLetterheadHandler(
            $repository,
            $clock,
            new DomainEventPublisher($this->createStub(EventBusInterface::class)),
            new InvoiceNumberingUniqueness($repository),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('Clinic with ID "%s" not found.', self::CLINIC_ID));

        $handler(new UpdateClinicLetterhead(clinicId: self::CLINIC_ID));
    }

    public function testTheEventCarriesWhetherTheLetterheadWasCleared(): void
    {
        $event = new ClinicLetterheadUpdated(self::CLINIC_ID, true);

        self::assertSame(self::CLINIC_ID, $event->aggregateId());
        self::assertSame(['clinicId' => self::CLINIC_ID, 'isEmpty' => true], $event->payload());
        self::assertSame('clinic.clinic-letterhead.updated.v1', $event->name());
    }

    private function handler(Clinic $clinic, bool $expectsPublish): UpdateClinicLetterheadHandler
    {
        $repository = $this->createMock(ClinicRepositoryInterface::class);
        $repository->method('findById')->willReturn($clinic);
        $repository->expects(self::once())->method('save')->with($clinic);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-22T10:00:00Z'));

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects($expectsPublish ? self::once() : self::never())->method('publish');

        return new UpdateClinicLetterheadHandler($repository, $clock, new DomainEventPublisher($eventBus), new InvoiceNumberingUniqueness($repository));
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
