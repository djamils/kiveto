<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Service;

use App\Context\ClinicListing\Application\Command\GeocodeClinicListing\GeocodeClinicListing;
use App\Context\ClinicListing\Application\Port\ClinicProfileInterface;
use App\Context\ClinicListing\Domain\ClinicListing;
use App\Context\ClinicListing\Domain\ClinicListingId;
use App\Context\ClinicListing\Domain\Repository\ClinicListingRepositoryInterface;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;

/**
 * Re-reads a clinic from Clinic and rewrites its projection (created on the
 * first read, with an id of its own). When the normalised address changed,
 * its geocoding is queued on `async_external` in the same transaction
 * (implicit outbox): it only runs once the change is committed.
 */
final readonly class ClinicListingRefresher
{
    public function __construct(
        private ClinicProfileInterface $clinics,
        private ClinicListingRepositoryInterface $listings,
        private ExternalApiCommandBusInterface $externalApiCommandBus,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return bool false when Clinic does not know the clinic (nothing written)
     */
    public function refresh(string $clinicId): bool
    {
        $profile = $this->clinics->profileOf($clinicId);

        if (null === $profile) {
            return false;
        }

        $listing = $this->listings->findByClinicId($clinicId);

        if (null === $listing) {
            $listing = ClinicListing::project(ClinicListingId::fromString($this->uuids->generate()), $clinicId, $profile, $this->clock->now());
            $geocode = null !== $listing->addressHash();
        } else {
            $geocode = $listing->refresh($profile, $this->clock->now());
        }

        $this->listings->save($listing);

        if ($geocode) {
            $this->externalApiCommandBus->send(new GeocodeClinicListing($clinicId));
        }

        return true;
    }
}
