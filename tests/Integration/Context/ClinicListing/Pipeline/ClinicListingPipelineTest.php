<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClinicListing\Pipeline;

use App\Context\Clinic\Application\Command\Clinic\ActivateClinicGroup\ActivateClinicGroup;
use App\Context\Clinic\Application\Command\Clinic\ChangeClinicStatus\ChangeClinicStatus;
use App\Context\Clinic\Application\Command\Clinic\RenameClinic\RenameClinic;
use App\Context\Clinic\Application\Command\Clinic\SuspendClinicGroup\SuspendClinicGroup;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicLetterhead\UpdateClinicLetterhead;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable\LetterheadEmbeddable;
use App\Context\ClinicListing\Application\Command\GeocodeClinicListing\GeocodeClinicListing;
use App\Context\ClinicListing\Application\Command\UpdateListingSettings\UpdateListingSettings;
use App\Context\ClinicListing\Application\Query\GetClinicListing\ClinicListingDetail;
use App\Context\ClinicListing\Application\Query\GetClinicListing\GetClinicListing;
use App\Context\ClinicListing\Application\Query\GetListingSettings\GetListingSettings;
use App\Context\ClinicListing\Application\Query\GetListingSettings\ListingSettingsView;
use App\Context\ClinicListing\Domain\ClinicListing;
use App\Context\ClinicListing\Domain\Repository\ClinicListingRepositoryInterface;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicGroupEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Tests\Support\MessengerDrain;
use App\Tests\Support\RecordingExternalApiCommandBus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * AC 78 through the real buses: Clinic's changes reach the listing through
 * ClinicProfileChangedIntegrationEvent and its consumer, a changed address is
 * geocoded again on `async_external` (FakeAddressGeocoder) and an unchanged
 * one is not, and a suspended clinic or clinic group makes the listing
 * inactive until it is reactivated, settings kept.
 */
final class ClinicListingPipelineTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC = '01960000-0000-7000-8000-0000000b3c01';
    private const string GROUP  = '01960000-0000-7000-8000-0000000b3a01';

    protected function setUp(): void
    {
        ClinicGroupEntityFactory::new()->withId(self::GROUP)->withName('Réseau VetoFrance')->create();
        ClinicEntityFactory::new()
            ->withId(self::CLINIC)
            ->withName('Clinique du Parc')
            ->withGroupId(self::GROUP)
            ->withLetterhead(new LetterheadEmbeddable(streetLine1: '18 rue des Abbesses', postalCode: '75018', city: 'Paris', countryCode: 'FR', phone: '+33142551890'))
            ->withOpeningHours(['week' => [1 => [['opensAt' => '09:00', 'closesAt' => '19:00']]], 'closures' => []])
            ->create(['timeZone' => 'Europe/Paris'])
        ;

        self::externalBus()->reset();
    }

    public function testTheListingFollowsTheClinicAndGeocodesOnlyAChangedAddress(): void
    {
        $this->bus()->dispatch(new UpdateListingSettings(self::CLINIC, true, true, ['NAC']));

        // Projected at once, its geocoding queued
        $listing = $this->listing();
        self::assertSame('Clinique du Parc', $listing->profile()->name());
        self::assertNull($listing->coordinates());
        self::assertCount(1, self::externalBus()->recorded(GeocodeClinicListing::class));

        MessengerDrain::drain(self::getContainer(), ['async_external', 'async']);
        $paris = $this->listing()->coordinates();
        self::assertNotNull($paris);
        self::assertEqualsWithDelta(48.8566, $paris->latitude(), 0.03);

        // Renamed: the new name, no new geocoding
        self::externalBus()->reset();
        $this->bus()->dispatch(new RenameClinic(self::CLINIC, 'Clinique du Lac'));
        MessengerDrain::drain(self::getContainer(), ['async_external', 'async']);
        self::assertSame('Clinique du Lac', $this->detail()?->name);
        self::assertSame([], self::externalBus()->recorded(GeocodeClinicListing::class));

        // The same address written differently: still no geocoding
        $this->bus()->dispatch(new UpdateClinicLetterhead(self::CLINIC, streetLine1: '18  RUE DES ABBESSES', postalCode: '75018', city: 'PARIS', countryCode: 'FR', phone: '+33142551890'));
        MessengerDrain::drain(self::getContainer(), ['async_external', 'async']);
        self::assertSame([], self::externalBus()->recorded(GeocodeClinicListing::class));
        self::assertSame('18 RUE DES ABBESSES', $this->detail()?->address?->line);

        // Moved to Lyon: geocoded again
        $this->bus()->dispatch(new UpdateClinicLetterhead(self::CLINIC, streetLine1: '7 quai Saint-Antoine', postalCode: '69002', city: 'Lyon', countryCode: 'FR', phone: '+33478920145'));
        MessengerDrain::drain(self::getContainer(), ['async_external', 'async']);
        self::assertCount(1, self::externalBus()->recorded(GeocodeClinicListing::class));
        $lyon = $this->listing()->coordinates();
        self::assertNotNull($lyon);
        self::assertEqualsWithDelta(45.764, $lyon->latitude(), 0.03);
        self::assertSame('+33478920145', $this->detail()?->phone);
    }

    public function testASuspendedClinicOrGroupLeavesTheDirectoryAndComesBackWithItsSettings(): void
    {
        $this->bus()->dispatch(new UpdateListingSettings(self::CLINIC, true, false, ['NAC', 'Urgences']));
        MessengerDrain::drain(self::getContainer(), ['async_external', 'async']);
        self::assertNotNull($this->detail());

        $this->bus()->dispatch(new SuspendClinicGroup(self::GROUP));
        MessengerDrain::drain(self::getContainer(), ['async_external', 'async']);
        self::assertFalse($this->listing()->isActive());
        self::assertNull($this->detail());

        $this->bus()->dispatch(new ActivateClinicGroup(self::GROUP));
        MessengerDrain::drain(self::getContainer(), ['async_external', 'async']);
        self::assertSame(['NAC', 'Urgences'], $this->listedDetail()->tags);

        $this->bus()->dispatch(new ChangeClinicStatus(self::CLINIC, ClinicStatus::SUSPENDED));
        MessengerDrain::drain(self::getContainer(), ['async_external', 'async']);
        self::assertNull($this->detail());

        $this->bus()->dispatch(new ChangeClinicStatus(self::CLINIC, ClinicStatus::ACTIVE));
        MessengerDrain::drain(self::getContainer(), ['async_external', 'async']);
        self::assertNotNull($this->detail());

        $bus = self::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);
        $settings = $bus->ask(new GetListingSettings(self::CLINIC));
        \assert($settings instanceof ListingSettingsView);
        self::assertSame([true, false, ['NAC', 'Urgences']], [$settings->listed, $settings->acceptsNewClients, $settings->tags]);
    }

    private function listing(): ClinicListing
    {
        $repository = self::getContainer()->get(ClinicListingRepositoryInterface::class);
        \assert($repository instanceof ClinicListingRepositoryInterface);
        $listing = $repository->findByClinicId(self::CLINIC);
        \assert(null !== $listing);

        return $listing;
    }

    private function detail(): ?ClinicListingDetail
    {
        $bus = self::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);
        $detail = $bus->ask(new GetClinicListing($this->listing()->id()->toString()));
        \assert(null === $detail || $detail instanceof ClinicListingDetail);

        return $detail;
    }

    private function listedDetail(): ClinicListingDetail
    {
        $detail = $this->detail();
        \assert(null !== $detail, 'The listing should be listed and active.');

        return $detail;
    }

    private function bus(): CommandBusInterface
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }

    private static function externalBus(): RecordingExternalApiCommandBus
    {
        $bus = self::getContainer()->get(RecordingExternalApiCommandBus::class);
        \assert($bus instanceof RecordingExternalApiCommandBus);

        return $bus;
    }
}
