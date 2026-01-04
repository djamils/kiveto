<?php

declare(strict_types=1);

namespace App\Fixtures\Dataset;

use App\Fixtures\IdentityAccess\Factory\ClinicUserFactory;
use App\Fixtures\IdentityAccess\Story\ClinicVetStory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'clinic')]
final class ClinicDataset extends Story
{
    public function build(): void
    {
        ClinicVetStory::load();

        ClinicUserFactory::createMany(5);
    }
}
