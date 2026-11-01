<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Command\GeocodeClinicListing;

use App\Context\ClinicListing\Application\Command\AttachListingCoordinates\AttachListingCoordinates;
use App\Context\ClinicListing\Application\Port\AddressGeocoderInterface;
use App\Context\ClinicListing\Domain\Repository\ClinicListingRepositoryInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs on the external worker, outside any transaction. A service outage is
 * rethrown so that Messenger retries; once the retries are spent the listing
 * keeps its previous coordinates. The answer goes with the hash of the
 * address it is for: AttachListingCoordinates ignores it if the address has
 * changed meanwhile.
 */
#[AsMessageHandler(bus: 'messenger.bus.external_api')]
final readonly class GeocodeClinicListingHandler
{
    public function __construct(
        private ClinicListingRepositoryInterface $listings,
        private AddressGeocoderInterface $geocoder,
        private CommandBusInterface $commandBus,
    ) {
    }

    /**
     * @throws ExternalServiceUnavailable
     */
    public function __invoke(GeocodeClinicListing $command): void
    {
        $listing = $this->listings->findByClinicId($command->clinicId);
        $address = $listing?->profile()->address();
        $hash    = $listing?->addressHash();

        if (null === $address || null === $hash) {
            return;
        }

        $coordinates = $this->geocoder->geocode($address);

        $this->commandBus->dispatch(new AttachListingCoordinates(
            clinicId: $command->clinicId,
            addressHash: $hash,
            latitude: $coordinates?->latitude(),
            longitude: $coordinates?->longitude(),
        ));
    }
}
