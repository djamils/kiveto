<?php

declare(strict_types=1);

namespace App\Fixtures\Dataset;

use App\Fixtures\AccessControl\Story\ClinicMembershipDataStory;
use App\Fixtures\Client\Story\ClientDataStory;
use App\Fixtures\Clinic\Story\ClinicDataStory;
use App\Fixtures\IdentityAccess\Factory\ClinicUserFactory;
use App\Fixtures\IdentityAccess\Story\ClinicVetStory;
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

        // Create memberships (assign users to clinics)
        ClinicMembershipDataStory::load();

        // Create Client BC data
        ClientDataStory::load();
    }
}
