<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Adapter\Clinic;

use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Infrastructure\Adapter\Clinic\QueryBusClinicOpeningHours;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

final class QueryBusClinicOpeningHoursTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c001';

    public function testTheHoursStatusAndTimeZoneComeFromTheClinicSettings(): void
    {
        $bus   = new AnsweringQueryBus([GetClinicSettings::class => self::settings('active')]);
        $hours = new QueryBusClinicOpeningHours($bus)->of(ClinicId::fromString(self::CLINIC));

        self::assertNotNull($hours);
        self::assertSame([true, 'America/Martinique', ['2026-04-15']], [$hours->active, $hours->timeZone->getName(), $hours->closedDates]);
        self::assertSame([['opensAt' => '08:00', 'closesAt' => '12:30'], ['opensAt' => '12:30', 'closesAt' => '18:00']], $hours->slotsOn('2026-04-13'));
        self::assertSame([], $hours->slotsOn('2026-04-14'), 'closed on Tuesdays');
        self::assertSame([], $hours->slotsOn('2026-04-15'), 'exceptional closure');
        self::assertInstanceOf(GetClinicSettings::class, $bus->asked[0]);
        self::assertSame(self::CLINIC, $bus->asked[0]->clinicId);
    }

    public function testASuspendedClinicIsNotActiveAndAnUnknownOneHasNoHours(): void
    {
        self::assertFalse(new QueryBusClinicOpeningHours(new AnsweringQueryBus([GetClinicSettings::class => self::settings('suspended')]))->of(ClinicId::fromString(self::CLINIC))?->active);
        self::assertNull(new QueryBusClinicOpeningHours(new AnsweringQueryBus())->of(ClinicId::fromString(self::CLINIC)));
    }

    private static function settings(string $status): ClinicSettingsView
    {
        return new ClinicSettingsView(
            id: self::CLINIC,
            name: 'Clinique du Lamentin',
            slug: 'clinique-du-lamentin',
            status: $status,
            timeZone: 'America/Martinique',
            locale: 'fr-FR',
            countryCode: 'FR',
            currencyCode: 'EUR',
            letterhead: [],
            week: [1 => [['opensAt' => '08:00', 'closesAt' => '12:30'], ['opensAt' => '12:30', 'closesAt' => '18:00']]],
            closures: [['date' => '2026-04-15', 'reason' => 'Inventaire']],
            billing: [],
        );
    }
}
