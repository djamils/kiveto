<?php

declare(strict_types=1);

namespace App\Fixtures\System\IdentityAccess\Story;

use App\Fixtures\System\IdentityAccess\Factory\BackofficeUserFactory;
use Zenstruck\Foundry\Story;

final class BackofficeAdminStory extends Story
{
    public function build(): void
    {
        BackofficeUserFactory::new()
            ->withEmail('admin@kiveto.local')
            ->withPlainPassword('admin')
            ->create()
        ;
    }
}
