<?php

declare(strict_types=1);

namespace App\Fixtures\Dataset;

use App\Fixtures\Context\Animal\Story\PortalPetsAnimalStory;
use App\Fixtures\Context\ClientPortal\Story\ClientPortalStatesStory;
use App\Fixtures\Context\ClientPortal\Story\PortalBookingStory;
use App\Fixtures\Context\ClientPortal\Story\PortalDocumentsStory;
use App\Fixtures\Context\ClientPortal\Story\PortalPetsStory;
use App\Fixtures\Context\Scheduling\Story\PortalPetsAppointmentsStory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Fixtures\System\IdentityAccess\Story\PortalUserStory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'portal')]
final class PortalDataset extends Story
{
    public function build(): void
    {
        PortalUserStory::load();

        // Their links to the clinics (already loaded with the clinic dataset).
        ClientPortalStatesStory::load();

        // What the clinic published on their Portal: invoices, ordonnances, reports.
        PortalDocumentsStory::load();

        // Their pets: the animals in every Portal state, the appointments of
        // the home, then the published visits of the timeline.
        PortalPetsAnimalStory::load();
        PortalPetsAppointmentsStory::load();
        PortalPetsStory::load();

        // Their appointments and online bookings: upcoming, pending, withdrawn,
        // a long history, the cancellation window cases, and the clinics
        // where they cannot book (booking off, empty week).
        PortalBookingStory::load();

        PortalUserFactory::createMany(5);
    }
}
