<?php

declare(strict_types=1);

namespace App\Fixtures\IdentityAccess\Story;

use App\Fixtures\IdentityAccess\Factory\PortalUserFactory;
use Zenstruck\Foundry\Story;

final class PortalUserStory extends Story
{
    public function build(): void
    {
        PortalUserFactory::new()
            ->withEmail('user@local.test')
            ->withPlainPassword('user')
            ->create();
    }
}
