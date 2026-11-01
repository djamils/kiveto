<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClinicListing\Application\Command;

use App\Context\ClinicListing\Application\Command\AttachListingCoordinates\AttachListingCoordinates;
use App\Context\ClinicListing\Application\Command\AttachListingCoordinates\AttachListingCoordinatesHandler;
use App\Context\ClinicListing\Application\Command\GeocodeClinicListing\GeocodeClinicListing;
use App\Context\ClinicListing\Application\Command\GeocodeClinicListing\GeocodeClinicListingHandler;
use App\Context\ClinicListing\Application\Command\RebuildClinicListings\RebuildClinicListings;
use App\Context\ClinicListing\Application\Command\RebuildClinicListings\RebuildClinicListingsHandler;
use App\Context\ClinicListing\Application\Command\RefreshClinicListing\RefreshClinicListing;
use App\Context\ClinicListing\Application\Command\RefreshClinicListing\RefreshClinicListingHandler;
use App\Context\ClinicListing\Application\Command\UpdateListingSettings\UpdateListingSettings;
use App\Context\ClinicListing\Application\Command\UpdateListingSettings\UpdateListingSettingsHandler;
use App\Context\ClinicListing\Application\Service\ClinicListingRefresher;
use App\Context\ClinicListing\Domain\Exception\InvalidListingSettingsException;
use App\Context\ClinicListing\Domain\ListingCoordinates;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Support\RecordingCommandBus;
use App\Tests\Unit\Context\ClinicListing\Support\ClinicListingFakes;
use PHPUnit\Framework\TestCase;

/**
 * AC 78, AC 271 (handlers): the projection follows Clinic, geocoding is
 * queued only when the normalised address changes, the geocoder's answer is
 * attached to the address it was for, and the settings refresh at once.
 */
final class ListingCommandHandlersTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-0000000a2c01';
    private const string OTHER  = '01960000-0000-7000-8000-0000000a2c02';

    private ClinicListingFakes $fakes;

    protected function setUp(): void
    {
        $this->fakes = new ClinicListingFakes();
    }

    public function testAFirstRefreshProjectsTheClinicWithItsOwnIdAndQueuesItsGeocoding(): void
    {
        $this->fakes->profiles[self::CLINIC] = ClinicListingFakes::profile();

        new RefreshClinicListingHandler($this->refresher())(new RefreshClinicListing(self::CLINIC));

        $listing = $this->fakes->listings[self::CLINIC];
        self::assertSame('01960000-0000-7000-8000-000000000001', $listing->id()->toString());
        self::assertNotSame(self::CLINIC, $listing->id()->toString());
        self::assertSame('2026-11-02 09:00:00', $listing->refreshedAt()->format('Y-m-d H:i:s'));
        self::assertCount(1, $this->fakes->sent);
        self::assertInstanceOf(GeocodeClinicListing::class, $this->fakes->sent[0]);
        self::assertSame(self::CLINIC, $this->fakes->sent[0]->clinicId);
    }

    public function testARefreshThatLeavesTheAddressAsItIsQueuesNoGeocoding(): void
    {
        $this->fakes->profiles[self::CLINIC] = ClinicListingFakes::profile();
        $handler                             = new RefreshClinicListingHandler($this->refresher());
        $handler(new RefreshClinicListing(self::CLINIC));
        $this->fakes->sent = [];

        $this->fakes->profiles[self::CLINIC] = ClinicListingFakes::profile(name: 'Clinique du Lac', line: '18 RUE DES ABBESSES', active: false);
        $handler(new RefreshClinicListing(self::CLINIC));

        self::assertSame('Clinique du Lac', $this->fakes->listings[self::CLINIC]->profile()->name());
        self::assertFalse($this->fakes->listings[self::CLINIC]->isActive());
        self::assertSame('01960000-0000-7000-8000-000000000001', $this->fakes->listings[self::CLINIC]->id()->toString());
        self::assertSame([], $this->fakes->sent);

        $this->fakes->profiles[self::CLINIC] = ClinicListingFakes::profile(line: '7 quai Saint-Antoine', city: 'Lyon');
        $handler(new RefreshClinicListing(self::CLINIC));
        self::assertCount(1, $this->fakes->sent);
    }

    public function testAClinicWithoutAddressOrUnknownToClinicQueuesNothing(): void
    {
        $this->fakes->profiles[self::CLINIC] = ClinicListingFakes::profile(line: null);
        $handler                             = new RefreshClinicListingHandler($this->refresher());

        $handler(new RefreshClinicListing(self::CLINIC));
        $handler(new RefreshClinicListing(self::OTHER));

        self::assertSame([self::CLINIC], array_keys($this->fakes->listings));
        self::assertSame([], $this->fakes->sent);
    }

    public function testARebuildProjectsEveryClinicClinicKnows(): void
    {
        $this->fakes->profiles[self::CLINIC] = ClinicListingFakes::profile();
        $this->fakes->profiles[self::OTHER]  = ClinicListingFakes::profile(name: 'Clinique de Lyon', city: 'Lyon');

        self::assertSame(2, new RebuildClinicListingsHandler($this->fakes, $this->refresher())(new RebuildClinicListings()));
        self::assertSame([self::CLINIC, self::OTHER], array_keys($this->fakes->listings));
        self::assertCount(2, $this->fakes->sent);

        // A clinic listed by Clinic but gone when read is not counted
        $this->fakes->ghostIds = [self::OTHER];
        unset($this->fakes->profiles[self::OTHER]);
        self::assertSame(1, new RebuildClinicListingsHandler($this->fakes, $this->refresher())(new RebuildClinicListings()));
    }

    public function testTheSettingsAreSavedAndTheListingRefreshedAtOnce(): void
    {
        $this->fakes->profiles[self::CLINIC] = ClinicListingFakes::profile();

        new UpdateListingSettingsHandler($this->fakes, $this->refresher())(new UpdateListingSettings(self::CLINIC, true, false, ['NAC', ' nac ']));

        $settings = $this->fakes->settings[self::CLINIC];
        self::assertSame([true, false, ['NAC']], [$settings->isListed(), $settings->acceptsNewClients(), $settings->tags()]);
        self::assertArrayHasKey(self::CLINIC, $this->fakes->listings);
    }

    public function testRefusedSettingsSaveNothing(): void
    {
        $this->fakes->profiles[self::CLINIC] = ClinicListingFakes::profile();

        try {
            new UpdateListingSettingsHandler($this->fakes, $this->refresher())(new UpdateListingSettings(self::CLINIC, true, true, ['X']));
            self::fail('The tag should be refused.');
        } catch (InvalidListingSettingsException) {
        }

        self::assertSame([], $this->fakes->settings);
        self::assertSame([], $this->fakes->listings);
    }

    public function testTheGeocoderAnswerIsAttachedWithTheHashItWasFor(): void
    {
        $this->fakes->profiles[self::CLINIC] = ClinicListingFakes::profile();
        $this->refresher()->refresh(self::CLINIC);
        $this->fakes->coordinates = ListingCoordinates::fromDegrees(48.884, 2.338);

        $bus = $this->attachingBus();
        new GeocodeClinicListingHandler($this->fakes, $this->fakes, $bus)(new GeocodeClinicListing(self::CLINIC));

        self::assertSame(['18 rue des Abbesses 75018 Paris'], $this->fakes->geocoded);
        $attach = $bus->recorded(AttachListingCoordinates::class)[0];
        self::assertSame([self::CLINIC, $this->fakes->listings[self::CLINIC]->addressHash(), 48.884, 2.338], [$attach->clinicId, $attach->addressHash, $attach->latitude, $attach->longitude]);
        self::assertTrue(ListingCoordinates::fromDegrees(48.884, 2.338)->equals($this->fakes->listings[self::CLINIC]->coordinates() ?? ListingCoordinates::fromDegrees(0, 0)));

        // Not found: attached as none
        $this->fakes->coordinates = null;
        new GeocodeClinicListingHandler($this->fakes, $this->fakes, $this->attachingBus())(new GeocodeClinicListing(self::CLINIC));
        self::assertNull($this->fakes->listings[self::CLINIC]->coordinates());
    }

    public function testAnOutageIsRethrownForMessengerToRetryAndTheCoordinatesStay(): void
    {
        $this->fakes->profiles[self::CLINIC] = ClinicListingFakes::profile();
        $this->refresher()->refresh(self::CLINIC);
        $hash = (string) $this->fakes->listings[self::CLINIC]->addressHash();
        $this->fakes->listings[self::CLINIC]->attachCoordinates($hash, ListingCoordinates::fromDegrees(48.884, 2.338));
        $this->fakes->geocoderFailure = ExternalServiceUnavailable::unreachable('Géoplateforme geocoder');

        $bus = $this->attachingBus();

        try {
            new GeocodeClinicListingHandler($this->fakes, $this->fakes, $bus)(new GeocodeClinicListing(self::CLINIC));
            self::fail('The outage should be rethrown.');
        } catch (ExternalServiceUnavailable) {
        }

        self::assertSame([], $bus->recorded(AttachListingCoordinates::class));
        self::assertNotNull($this->fakes->listings[self::CLINIC]->coordinates());
    }

    public function testNothingIsGeocodedWithoutListingOrAddress(): void
    {
        $this->fakes->profiles[self::CLINIC] = ClinicListingFakes::profile(line: null);
        $this->refresher()->refresh(self::CLINIC);
        $bus = $this->attachingBus();

        new GeocodeClinicListingHandler($this->fakes, $this->fakes, $bus)(new GeocodeClinicListing(self::CLINIC));
        new GeocodeClinicListingHandler($this->fakes, $this->fakes, $bus)(new GeocodeClinicListing(self::OTHER));

        self::assertSame([], $this->fakes->geocoded);
        self::assertSame([], $bus->recorded(AttachListingCoordinates::class));
    }

    public function testAStaleAnswerOrAnUnknownListingChangesNothing(): void
    {
        $this->fakes->profiles[self::CLINIC] = ClinicListingFakes::profile();
        $this->refresher()->refresh(self::CLINIC);
        $saves   = $this->fakes->saves;
        $handler = new AttachListingCoordinatesHandler($this->fakes);

        $handler(new AttachListingCoordinates(self::CLINIC, 'an-older-address', 45.76, 4.83));
        $handler(new AttachListingCoordinates(self::OTHER, 'whatever', 45.76, 4.83));
        self::assertSame($saves, $this->fakes->saves);
        self::assertNull($this->fakes->listings[self::CLINIC]->coordinates());

        // Half a point is no point
        $handler(new AttachListingCoordinates(self::CLINIC, (string) $this->fakes->listings[self::CLINIC]->addressHash(), 45.76, null));
        self::assertSame($saves + 1, $this->fakes->saves);
        self::assertNull($this->fakes->listings[self::CLINIC]->coordinates());
    }

    private function refresher(): ClinicListingRefresher
    {
        return new ClinicListingRefresher($this->fakes, $this->fakes, $this->fakes, $this->fakes, new FrozenClock(new \DateTimeImmutable('2026-11-02 09:00:00')));
    }

    /** A command bus that runs AttachListingCoordinates against the fakes. */
    private function attachingBus(): RecordingCommandBus
    {
        $handler = new AttachListingCoordinatesHandler($this->fakes);

        return new RecordingCommandBus(new class($handler) implements CommandBusInterface {
            public function __construct(private readonly AttachListingCoordinatesHandler $handler)
            {
            }

            public function dispatch(object $command, object ...$stamps): mixed
            {
                \assert($command instanceof AttachListingCoordinates);
                ($this->handler)($command);

                return null;
            }
        });
    }
}
