<?php

declare(strict_types=1);

namespace App\Fixtures\Dataset;

use App\Fixtures\Context\Admission\Story\FluxDayStory;
use App\Fixtures\Context\Animal\Story\AnimalDataStory;
use App\Fixtures\Context\Billing\Story\BillingDemoStory;
use App\Fixtures\Context\Billing\Story\BillingRegisterStory;
use App\Fixtures\Context\Billing\Story\DirectoryBalancesStory;
use App\Fixtures\Context\Billing\Story\ProfessionalInvoicesStory;
use App\Fixtures\Context\Catalog\Story\CompanionClinicCatalogStory;
use App\Fixtures\Context\Client\Story\ClientDataStory;
use App\Fixtures\Context\Client\Story\ClientSheetStatesStory;
use App\Fixtures\Context\Client\Story\DirectoryCrowdStory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\Context\Clinic\Story\ClinicMembershipDataStory;
use App\Fixtures\Context\Clinic\Story\ClinicStaffProfileDataStory;
use App\Fixtures\Context\Clinic\Story\TeamDemoStory;
use App\Fixtures\Context\Consultation\Story\ConsultationDemoStory;
use App\Fixtures\Context\Consultation\Story\ConsultationRegisterStory;
use App\Fixtures\Context\Inventory\Story\InventoryDemoStory;
use App\Fixtures\Context\Prescription\Story\PrescriptionDemoStory;
use App\Fixtures\Context\Prescription\Story\PrescriptionRegisterStory;
use App\Fixtures\Context\Procurement\Story\ProcurementDemoStory;
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
        ProfessionalInvoicesStory::load();

        // The flow board's day: passages paid this morning, a no-show, a late
        // booking, an arrival check-in, a booking with a balance due.
        FluxDayStory::load();

        // The suppliers and what they sell, then the stock: both are built on
        // the articles the catalogue tracks, the stock's receipts on the
        // suppliers.
        ProcurementDemoStory::load();
        InventoryDemoStory::load();

        // The three states of the client sheet (normal, busy, brand new): last,
        // so that the demo stories above never pick their visits as their own.
        ClientSheetStatesStory::load();

        // The Paris register of ordonnances at full size, every state of the
        // register and its sheet (after the visits sharing these animals).
        PrescriptionRegisterStory::load();

        // The Paris register of consultations in every state (to close, emergencies,
        // surgeries, analyses, follow-ups): before the billing register invoices them.
        ConsultationRegisterStory::load();

        // The Paris register of invoices at full size, every state of the
        // register, its sheet and the till (invoices the visits above).
        BillingRegisterStory::load();

        // The team page in every state (arriving, never signed in, ended,
        // switched off) and the sign-ins it reads: last, so that no story
        // above picks one of these members for its visits or its stock.
        TeamDemoStory::load();
    }
}
