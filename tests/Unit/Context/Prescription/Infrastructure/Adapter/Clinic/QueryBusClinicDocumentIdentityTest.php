<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Prescription\Infrastructure\Adapter\Clinic;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\Prescription\Infrastructure\Adapter\Clinic\QueryBusClinicDocumentIdentity;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryBusClinicDocumentIdentity::class)]
final class QueryBusClinicDocumentIdentityTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-000000000001';

    public function testTheClinicIsReadFromTheClinicContext(): void
    {
        $bus = new AnsweringQueryBus([GetClinic::class => new ClinicDto(
            id: self::CLINIC_ID,
            name: 'Clinique Montmartre',
            slug: 'clinique-montmartre',
            timeZone: 'Europe/Paris',
            locale: 'fr-FR',
            countryCode: 'FR',
            jurisdictionCode: null,
            currencyCode: 'EUR',
            status: ClinicStatus::ACTIVE,
            clinicGroupId: null,
            createdAt: '2026-01-01T00:00:00+00:00',
            updatedAt: '2026-01-01T00:00:00+00:00',
            brandColor: '#4f86c6',
        )]);

        $clinic = new QueryBusClinicDocumentIdentity($bus)->forClinic(self::CLINIC_ID);

        self::assertSame('Clinique Montmartre', $clinic?->name);
        self::assertSame('#4f86c6', $clinic->brandColor);
    }

    public function testAnUnknownClinicPrintsNoLetterhead(): void
    {
        self::assertNull(new QueryBusClinicDocumentIdentity(new AnsweringQueryBus())->forClinic(self::CLINIC_ID));
    }
}
