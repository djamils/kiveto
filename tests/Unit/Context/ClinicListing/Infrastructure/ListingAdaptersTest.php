<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClinicListing\Infrastructure;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Clinic\GetClinicGroup\ClinicGroupDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinicGroup\GetClinicGroup;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Clinic\Application\Query\Clinic\ListClinics\ClinicCollection;
use App\Context\Clinic\Application\Query\Clinic\ListClinics\ListClinics;
use App\Context\Clinic\Domain\Event\ClinicProfileChangedIntegrationEvent;
use App\Context\Clinic\Domain\ValueObject\ClinicGroupStatus;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\ClinicListing\Application\Command\RebuildClinicListings\RebuildClinicListings;
use App\Context\ClinicListing\Application\Command\RefreshClinicListing\RefreshClinicListing;
use App\Context\ClinicListing\Infrastructure\Adapter\Clinic\QueryBusClinicProfile;
use App\Context\ClinicListing\Infrastructure\Adapter\Scheduling\QueryBusOnlineBookingStatus;
use App\Context\ClinicListing\Infrastructure\Console\RebuildClinicListingsCommand;
use App\Context\ClinicListing\Infrastructure\Messaging\Consumer\ClinicProfileChangedIntegrationEventConsumer;
use App\Context\Scheduling\Application\Query\ListClinicsOpenToNewClients\ListClinicsOpenToNewClients;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ListingAdaptersTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-0000000a6c01';
    private const string GROUP  = '01960000-0000-7000-8000-0000000a6a01';

    public function testTheProfileIsReadFromClinicsQueries(): void
    {
        $bus = new AnsweringQueryBus([
            GetClinic::class         => self::clinic(ClinicStatus::ACTIVE, null),
            GetClinicSettings::class => self::settings('18 rue des Abbesses', 'Bâtiment B', [3 => [['opensAt' => '14:00', 'closesAt' => '19:00']], 1 => [['opensAt' => '09:00', 'closesAt' => '12:30'], ['opensAt' => '14:00', 'closesAt' => '19:00']]]),
        ]);

        $profile = new QueryBusClinicProfile($bus)->profileOf(self::CLINIC);

        self::assertNotNull($profile);
        self::assertSame(['Clinique du Parc', '+33142551890', 'Europe/Paris', true], [$profile->name(), $profile->phone(), $profile->timezone(), $profile->isActive()]);
        self::assertSame(['18 rue des Abbesses, Bâtiment B', '75018', 'Paris'], [$profile->address()?->line(), $profile->address()?->postalCode(), $profile->address()?->city()]);
        self::assertSame([
            ['day' => 1, 'opensAt' => '09:00', 'closesAt' => '12:30'],
            ['day' => 1, 'opensAt' => '14:00', 'closesAt' => '19:00'],
            ['day' => 3, 'opensAt' => '14:00', 'closesAt' => '19:00'],
        ], $profile->openingHours());
        self::assertSame([GetClinic::class, GetClinicSettings::class], array_map(static fn (object $query): string => $query::class, $bus->asked));
    }

    public function testAClinicIsActiveOnlyWithItsGroup(): void
    {
        $bus = new AnsweringQueryBus([
            GetClinic::class         => self::clinic(ClinicStatus::ACTIVE, self::GROUP),
            GetClinicSettings::class => self::settings(null, null, []),
            GetClinicGroup::class    => new ClinicGroupDto(self::GROUP, 'Réseau', ClinicGroupStatus::SUSPENDED, 'c'),
        ]);
        $adapter = new QueryBusClinicProfile($bus);

        $profile = $adapter->profileOf(self::CLINIC);
        self::assertNotNull($profile);
        self::assertFalse($profile->isActive());
        self::assertNull($profile->address());

        $bus->answer(GetClinicGroup::class, new ClinicGroupDto(self::GROUP, 'Réseau', ClinicGroupStatus::ACTIVE, 'c'));
        self::assertTrue(self::active($adapter));

        // A group Clinic cannot read does not hide the clinic
        $bus->answer(GetClinicGroup::class, null);
        self::assertTrue(self::active($adapter));

        $bus->answer(GetClinic::class, self::clinic(ClinicStatus::CLOSED, null));
        self::assertFalse(self::active($adapter));

        $bus->answer(GetClinic::class, null);
        self::assertNull($adapter->profileOf(self::CLINIC));
    }

    public function testEveryClinicIdIsListed(): void
    {
        $bus = new AnsweringQueryBus([ListClinics::class => new ClinicCollection([self::clinic(ClinicStatus::SUSPENDED, null)], 1)]);

        self::assertSame([self::CLINIC], new QueryBusClinicProfile($bus)->clinicIds());
        self::assertInstanceOf(ListClinics::class, $bus->asked[0]);
        self::assertNull($bus->asked[0]->status);
        self::assertSame([], new QueryBusClinicProfile(new AnsweringQueryBus())->clinicIds());
    }

    public function testSchedulingIsAskedOncePerPage(): void
    {
        $bus = new AnsweringQueryBus([ListClinicsOpenToNewClients::class => static fn (ListClinicsOpenToNewClients $q): array => \array_slice($q->clinicIds, 1)]);

        self::assertSame(['b'], new QueryBusOnlineBookingStatus($bus)->openToNewClients(['a', 'b']));
        self::assertSame([], new QueryBusOnlineBookingStatus(new AnsweringQueryBus())->openToNewClients(['a']));
    }

    public function testTheConsumerRefreshesTheListingAndTheConsoleRebuildsAll(): void
    {
        $bus = new class implements CommandBusInterface {
            /** @var list<object> */
            public array $dispatched = [];

            public function dispatch(object $command, object ...$stamps): mixed
            {
                $this->dispatched[] = $command;

                return $command instanceof RebuildClinicListings ? 3 : null;
            }
        };

        new ClinicProfileChangedIntegrationEventConsumer($bus)(new ClinicProfileChangedIntegrationEvent(self::CLINIC));
        self::assertInstanceOf(RefreshClinicListing::class, $bus->dispatched[0]);
        self::assertSame(self::CLINIC, $bus->dispatched[0]->clinicId);

        $tester = new CommandTester(new RebuildClinicListingsCommand($bus));
        self::assertSame(0, $tester->execute([]));
        self::assertInstanceOf(RebuildClinicListings::class, $bus->dispatched[1]);
        self::assertStringContainsString('3 clinic listing(s) rebuilt.', $tester->getDisplay());
    }

    private static function active(QueryBusClinicProfile $adapter): bool
    {
        $profile = $adapter->profileOf(self::CLINIC);
        self::assertNotNull($profile);

        return $profile->isActive();
    }

    private static function clinic(ClinicStatus $status, ?string $groupId): ClinicDto
    {
        return new ClinicDto(self::CLINIC, 'Clinique du Parc', 'parc', 'Europe/Paris', 'fr-FR', 'FR', null, 'EUR', $status, $groupId, 'c', 'u');
    }

    /**
     * @param array<int, list<array{opensAt: string, closesAt: string}>> $week
     */
    private static function settings(?string $line1, ?string $line2, array $week): ClinicSettingsView
    {
        return new ClinicSettingsView(
            self::CLINIC,
            'Clinique du Parc',
            'parc',
            'active',
            'Europe/Paris',
            'fr-FR',
            'FR',
            'EUR',
            ['streetLine1' => $line1, 'streetLine2' => $line2, 'postalCode' => '75018', 'city' => 'Paris', 'phone' => '+33142551890'],
            $week,
            [],
            [],
        );
    }
}
