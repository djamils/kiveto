<?php

declare(strict_types=1);

namespace App\Fixtures;

use App\Fixtures\Dataset\ClinicDataset;
use App\Fixtures\Dataset\PlatformDataset;
use App\Fixtures\Dataset\PortalDataset;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'dev')]
final class DevDataset extends Story
{
    public function build(): void
    {
        PlatformDataset::load();
        ClinicDataset::load();
        PortalDataset::load();
    }
}
