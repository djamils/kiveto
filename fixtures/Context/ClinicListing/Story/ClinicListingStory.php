<?php

declare(strict_types=1);

namespace App\Fixtures\Context\ClinicListing\Story;

use App\Context\Clinic\Application\Command\Clinic\ChangeClinicStatus\ChangeClinicStatus;
use App\Context\Clinic\Application\Command\Clinic\CreateClinic\CreateClinic;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicLetterhead\UpdateClinicLetterhead;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\ClinicListing\Application\Command\GeocodeClinicListing\GeocodeClinicListing;
use App\Context\ClinicListing\Application\Command\RebuildClinicListings\RebuildClinicListings;
use App\Context\ClinicListing\Application\Command\UpdateListingSettings\UpdateListingSettings;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\Context\Scheduling\Story\OnlineBookingStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Zenstruck\Foundry\Story;

/**
 * The Portal's clinic directory (slice 7) in every state its search and
 * cards show:
 *
 * | Clinic     | Listed | New clients | Online booking | Card                                                     |
 * |------------|--------|-------------|----------------|----------------------------------------------------------|
 * | Paris      | yes    | yes         | **no**         | 8 tags (the card's « +N »); Scheduling takes no new-     |
 * |            |        |             |                | client request there                                     |
 * | Lyon       | yes    | yes         | yes            | 2 tags; the « Nouveau client » requests of               |
 * |            |        |             |                | PortalOrganicArrivalStory                                |
 * | Vercors    | yes    | **no**      | no             | no address on file: **no coordinates**, absent from a    |
 * |            |        |             |                | search around a point, present in a search by name       |
 * | Calanques  | **no** | —           | —              | left out (the default)                                   |
 * | Bordeaux   | yes    | yes         | no             | listed, then **suspended** through Clinic: the projection |
 * |            |        |             |                | is inactive, the card « not found »                      |
 *
 * Every clinic is then projected, as `clinic-listing:rebuild` does at
 * deploy, and each address geocoded at once by the FakeAddressGeocoder of
 * the dev environment (no network); the geocodings the rebuild queued on
 * `async_external` find the same address and change nothing. The Bordeaux
 * clinic is created here: no Portal account, client or appointment relies
 * on it.
 */
final class ClinicListingStory extends Story
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly ExternalApiCommandBusInterface $externalBus,
        private readonly Connection $connection,
    ) {
    }

    public function build(): void
    {
        // The Vercors (no opening week, no address) is OnlineBookingStory's.
        OnlineBookingStory::load();

        $this->commandBus->dispatch(new UpdateListingSettings(
            ClinicDataStory::INDEPENDENT_CLINIC_ID,
            listed: true,
            acceptsNewClients: true,
            tags: ['Chats', 'Chiens', 'NAC', 'Urgences', 'Dentisterie', 'Imagerie', 'Comportement', 'Ostéopathie'],
        ));

        $this->commandBus->dispatch(new UpdateListingSettings(
            ClinicDataStory::GROUP_CLINIC_ID,
            listed: true,
            acceptsNewClients: true,
            tags: ['Équidés', 'NAC'],
        ));

        $this->commandBus->dispatch(new UpdateListingSettings(
            OnlineBookingStory::EMPTY_WEEK_CLINIC_ID,
            listed: true,
            acceptsNewClients: false,
            tags: ['Rurale'],
        ));

        $bordeaux = $this->suspendedClinic();

        $this->commandBus->dispatch(new RebuildClinicListings());
        $this->geocode();

        // Suspended once listed and placed: the projection goes inactive.
        $this->commandBus->dispatch(new ChangeClinicStatus($bordeaux, ClinicStatus::SUSPENDED));
        $this->commandBus->dispatch(new RebuildClinicListings());
    }

    /** A Bordeaux clinic that joined the directory; Kiveto suspends it below. */
    private function suspendedClinic(): string
    {
        $id = $this->commandBus->dispatch(new CreateClinic(
            name: 'Clinique Vétérinaire des Chartrons',
            slug: 'clinic-chartrons',
            timeZone: 'Europe/Paris',
            locale: 'fr-FR',
            countryCode: 'FR',
            currencyCode: 'EUR',
        ));
        \assert(\is_string($id));

        $this->commandBus->dispatch(new UpdateClinicLetterhead(
            clinicId: $id,
            legalName: 'SELARL Vétérinaire des Chartrons',
            streetLine1: '24 quai des Chartrons',
            postalCode: '33000',
            city: 'Bordeaux',
            countryCode: 'FR',
            phone: '+33556000000',
        ));

        $this->commandBus->dispatch(new UpdateListingSettings($id, listed: true, acceptsNewClients: true, tags: ['Chats', 'Chiens']));

        return $id;
    }

    /** What the external worker does with the queued geocodings, run here at once. */
    private function geocode(): void
    {
        $clinicIds = $this->connection->fetchFirstColumn('SELECT BIN_TO_UUID(clinic_id) FROM clinic_listing__clinic_listings WHERE address_hash IS NOT NULL');

        foreach ($clinicIds as $clinicId) {
            \assert(\is_string($clinicId));
            $this->externalBus->send(new GeocodeClinicListing($clinicId), new TransportNamesStamp(['sync']));
        }
    }
}
