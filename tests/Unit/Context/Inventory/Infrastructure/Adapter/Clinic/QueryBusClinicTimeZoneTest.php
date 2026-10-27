<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Infrastructure\Adapter\Clinic;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\Inventory\Infrastructure\Adapter\Clinic\QueryBusClinicTimeZone;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

final class QueryBusClinicTimeZoneTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-000000000001';

    public function testTheClinicTimeZoneIsReadFromTheClinicContext(): void
    {
        $bus = new AnsweringQueryBus([GetClinic::class => new ClinicDto(
            id: self::CLINIC_ID,
            name: 'Clinique de Cayenne',
            slug: 'clinique-de-cayenne',
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

        self::assertSame('America/Cayenne', new QueryBusClinicTimeZone($bus)->of(self::CLINIC_ID)->getName());
    }

    public function testAnUnknownClinicIsInParis(): void
    {
        self::assertSame('Europe/Paris', new QueryBusClinicTimeZone(new AnsweringQueryBus())->of(self::CLINIC_ID)->getName());
    }
}
