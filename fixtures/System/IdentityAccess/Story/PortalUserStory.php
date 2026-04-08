<?php

declare(strict_types=1);

namespace App\Fixtures\System\IdentityAccess\Story;

use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use Zenstruck\Foundry\Story;

final class PortalUserStory extends Story
{
    public function build(): void
    {
        PortalUserFactory::new()
            ->withEmail('user@kiveto.local')
            ->withPlainPassword('user')
            ->create()
        ;
    }
}
