<?php

declare(strict_types=1);

namespace App\Fixtures\Dataset;

use App\Fixtures\System\IdentityAccess\Factory\BackofficeUserFactory;
use App\Fixtures\System\IdentityAccess\Story\BackofficeAdminStory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'platform')]
final class PlatformDataset extends Story
{
    public function build(): void
    {
        BackofficeAdminStory::load();

        BackofficeUserFactory::createMany(3);
    }
}
