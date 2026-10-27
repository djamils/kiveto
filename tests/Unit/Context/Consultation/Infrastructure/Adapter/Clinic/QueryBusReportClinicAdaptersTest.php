<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Infrastructure\Adapter\Clinic;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\Consultation\Infrastructure\Adapter\Clinic\QueryBusClinicDocumentIdentity;
use App\Context\Consultation\Infrastructure\Adapter\Clinic\QueryBusPractitionerDirectory;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryBusClinicDocumentIdentity::class)]
#[CoversClass(QueryBusPractitionerDirectory::class)]
final class QueryBusReportClinicAdaptersTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-000000000001';
    private const string VET_ID    = '01960000-0000-7000-8000-0000000c0002';

    public function testTheClinicIsReadFromTheClinicContext(): void
    {
        $bus = new AnsweringQueryBus([GetClinic::class => new ClinicDto(
            id: self::CLINIC_ID,
            name: 'Clinique Montmartre',
            slug: 'clinique-montmartre',
            timeZone: 'America/Cayenne',
            locale: 'fr-FR',
            countryCode: 'FR',
            jurisdictionCode: null,
            currencyCode: 'EUR',
            status: ClinicStatus::ACTIVE,
            clinicGroupId: null,
            createdAt: '2026-01-01T00:00:00+00:00',
            updatedAt: '2026-01-01T00:00:00+00:00',
        )]);

        self::assertSame('Clinique Montmartre', new QueryBusClinicDocumentIdentity($bus)->forClinic(self::CLINIC_ID)?->name);
        self::assertSame('America/Cayenne', new QueryBusClinicDocumentIdentity($bus)->timeZone(self::CLINIC_ID)->getName());
        self::assertNull(new QueryBusClinicDocumentIdentity(new AnsweringQueryBus())->forClinic(self::CLINIC_ID));
        self::assertSame('Europe/Paris', new QueryBusClinicDocumentIdentity(new AnsweringQueryBus())->timeZone(self::CLINIC_ID)->getName(), 'An unknown clinic dates in Paris.');
    }

    public function testThePractitionerIsFoundAmongTheClinicVeterinarians(): void
    {
        $bus = new AnsweringQueryBus([ListClinicVeterinarians::class => [
            'not a veterinarian',
            new ClinicVeterinarianItem('01960000-0000-7000-8000-0000000c0009', 'VETERINARY', 'FULL_TIME', 'm-9', 'Paul Rousseau', 'DR'),
            new ClinicVeterinarianItem(self::VET_ID, 'VETERINARY', 'FULL_TIME', 'm-2', 'Claire Lambert', 'DR'),
        ]]);

        $practitioner = new QueryBusPractitionerDirectory($bus)->find(self::CLINIC_ID, self::VET_ID);

        self::assertSame('Claire Lambert', $practitioner?->displayName);
        self::assertSame('DR', $practitioner->professionalTitle);
    }

    public function testAPractitionerWhoIsNoLongerAVeterinarianOfTheClinicIsNotFound(): void
    {
        $bus = new AnsweringQueryBus([ListClinicVeterinarians::class => []]);

        self::assertNull(new QueryBusPractitionerDirectory($bus)->find(self::CLINIC_ID, self::VET_ID));
        self::assertNull(new QueryBusPractitionerDirectory(new AnsweringQueryBus())->find(self::CLINIC_ID, self::VET_ID));
    }
}
