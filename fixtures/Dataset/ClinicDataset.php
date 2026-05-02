<?php

declare(strict_types=1);

namespace App\Fixtures\Dataset;

use App\Fixtures\Context\Animal\Story\AnimalDataStory;
use App\Fixtures\Context\Client\Story\ClientDataStory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\Context\Clinic\Story\ClinicMembershipDataStory;
use App\Fixtures\Context\Clinic\Story\ClinicStaffProfileDataStory;
use App\Fixtures\Context\Patient\Story\PatientAdmissionScenariosStory;
use App\Fixtures\Context\Scheduling\Story\SchedulingPlanningBlockStory;
use App\Fixtures\Context\Scheduling\Story\SchedulingStory;
use App\Fixtures\System\AccessControl\Story\RolePermissionSeedStory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Fixtures\System\IdentityAccess\Story\ClinicVetStory;
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

        // Create Patient, Admission, and Regulatory BC scenario fixtures
        PatientAdmissionScenariosStory::load();

        // Seed Scheduling BC appointments (depends on users, memberships, owners, animals)
        SchedulingStory::load();

        // Seed PlanningBlock fixtures
        SchedulingPlanningBlockStory::load();

        // Seed Regulatory BC translation labels
        RegulatoryTranslationStory::load();
    }
}
