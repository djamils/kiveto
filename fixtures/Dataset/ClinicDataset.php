<?php

declare(strict_types=1);

namespace App\Fixtures\Dataset;

use App\Fixtures\Context\Animal\Story\AnimalDataStory;
use App\Fixtures\Context\Billing\Story\BillingDemoStory;
use App\Fixtures\Context\Billing\Story\DirectoryBalancesStory;
use App\Fixtures\Context\Catalog\Story\CompanionClinicCatalogStory;
use App\Fixtures\Context\Client\Story\ClientDataStory;
use App\Fixtures\Context\Client\Story\ClientSheetStatesStory;
use App\Fixtures\Context\Client\Story\DirectoryCrowdStory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\Context\Clinic\Story\ClinicMembershipDataStory;
use App\Fixtures\Context\Clinic\Story\ClinicStaffProfileDataStory;
use App\Fixtures\Context\Consultation\Story\ConsultationDemoStory;
use App\Fixtures\Context\Inventory\Story\InventoryDemoStory;
use App\Fixtures\Context\Prescription\Story\PrescriptionDemoStory;
use App\Fixtures\Context\Scheduling\Story\CalmDayClinicStory;
use App\Fixtures\Context\Scheduling\Story\SchedulingPlanningBlockStory;
use App\Fixtures\Context\Scheduling\Story\SchedulingStory;
use App\Fixtures\System\AccessControl\Story\RolePermissionSeedStory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Fixtures\System\IdentityAccess\Story\ClinicVetStory;
use App\Fixtures\System\PharmaceuticalRegistry\Story\ControlledSubstancesStory;
use App\Fixtures\System\PharmaceuticalRegistry\Story\CoreMedicationsStory;
use App\Fixtures\System\PharmaceuticalRegistry\Story\LivestockMedicationsStory;
use App\Fixtures\System\Taxation\Story\TaxonomyBootstrapStory;
use App\Fixtures\System\Translation\Story\RegulatoryTranslationStory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'clinic')]
final class ClinicDataset extends Story
{
    public function build(): void
    {
        // Create Clinic BC entities first
        ClinicDataStory::load();

        // Then create users
        ClinicVetStory::load();

        ClinicUserFactory::createMany(5);

        // Seed RBAC role_permissions (static reference data)
        RolePermissionSeedStory::load();

        // Create non-practitioner memberships (MANAGER, RECEPTIONIST)
        ClinicMembershipDataStory::load();

        // Onboard VET/ASV practitioners with StaffProfiles
        ClinicStaffProfileDataStory::load();

        // Create Client BC data
        ClientDataStory::load();

        // Create Animal BC data
        AnimalDataStory::load();

        // The Paris directory at a realistic size (several pages, every facet)
        DirectoryCrowdStory::load();

        // Seed Scheduling BC appointments (depends on users, memberships, owners, animals)
        SchedulingStory::load();

        // The quiet clinic of the group: its own patients and calm day
        CalmDayClinicStory::load();

        // Seed PlanningBlock fixtures
        SchedulingPlanningBlockStory::load();

        // Seed Regulatory BC translation labels
        RegulatoryTranslationStory::load();

        // Seed Taxation reference data (categories + FR regime), needed by Catalog pricing
        TaxonomyBootstrapStory::load();

        // Seed PharmaceuticalRegistry authorizations (must precede the catalog:
        // drug articles reference authorizations by commercial name)
        CoreMedicationsStory::load();
        ControlledSubstancesStory::load();
        LivestockMedicationsStory::load();

        // Seed the demo clinic's Catalog (acts, articles, packages, price lists)
        CompanionClinicCatalogStory::load();

        // Seed admissions + consultations in varied cockpit states (needs the
        // catalog and the animals above)
        ConsultationDemoStory::load();

        // Write the ordonnances of the consultations that prescribed something
        PrescriptionDemoStory::load();

        // Invoice the visits that are over (needs their consultations)
        BillingDemoStory::load();
        DirectoryBalancesStory::load();

        // Stock last: it is built on the articles the catalogue tracks.
        InventoryDemoStory::load();

        // The three states of the client sheet (normal, busy, brand new): last,
        // so that the demo stories above never pick their visits as their own.
        ClientSheetStatesStory::load();
    }
}
