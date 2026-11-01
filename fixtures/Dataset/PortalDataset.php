<?php

declare(strict_types=1);

namespace App\Fixtures\Dataset;

use App\Fixtures\Context\Animal\Story\PortalPetsAnimalStory;
use App\Fixtures\Context\Client\Story\ConsentsStory;
use App\Fixtures\Context\ClientPortal\Story\ClientPortalStatesStory;
use App\Fixtures\Context\ClientPortal\Story\PortalAccountStatesStory;
use App\Fixtures\Context\ClientPortal\Story\PortalBookingStory;
use App\Fixtures\Context\ClientPortal\Story\PortalDocumentsStory;
use App\Fixtures\Context\ClientPortal\Story\PortalOrganicArrivalStory;
use App\Fixtures\Context\ClientPortal\Story\PortalPetsStory;
use App\Fixtures\Context\ClientPortal\Story\PortalRequestsStory;
use App\Fixtures\Context\ClinicListing\Story\ClinicListingStory;
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

        // The clinic directory: listed clinics (without online booking, open
        // to new clients, not taking any, without coordinates), one left out
        // and one suspended, projected as `clinic-listing:rebuild` does.
        ClinicListingStory::load();

        // Their consents (none, partial, all; two files that diverge; an
        // archived client and its unarchived twin) with a history mixing the
        // clinic, the Portal and the « Reprise » of older consents.
        ConsentsStory::load();

        // Their accounts: verified e-mail, pending e-mail change, data
        // exports pending / ready / expired, and an account deleted.
        PortalAccountStatesStory::load();

        // Their requests: pet changes and renewals in every state (open,
        // urgent, done, refused, closed by a cancelled ordonnance), the
        // pre-visit questionnaires (editable, locked, opened in the cockpit),
        // and the clinics with an empty queue.
        PortalRequestsStory::load();

        // The organic arrival: accounts registered without invitation, the
        // clinics suggested from their mobile (open, pending « Rattachement »,
        // rejected) and their first appointments asked through the directory
        // (pending, confirmed, declined, expired).
        PortalOrganicArrivalStory::load();

        PortalUserFactory::createMany(5);
    }
}
