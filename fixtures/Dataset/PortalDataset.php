<?php

declare(strict_types=1);

namespace App\Fixtures\Dataset;

use App\Fixtures\IdentityAccess\Factory\PortalUserFactory;
use App\Fixtures\IdentityAccess\Story\PortalUserStory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'portal')]
final class PortalDataset extends Story
{
    public function build(): void
    {
        PortalUserStory::load();

        PortalUserFactory::createMany(5);
    }
}
