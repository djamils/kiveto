<?php

declare(strict_types=1);

namespace App\Fixtures\IdentityAccess\Story;

use App\Fixtures\IdentityAccess\Factory\BackofficeUserFactory;
use Zenstruck\Foundry\Story;

final class BackofficeAdminStory extends Story
{
    public function build(): void
    {
        BackofficeUserFactory::new()
            ->withEmail('admin.backoffice@local.test')
            ->withPlainPassword('admin')
            ->create();
    }
}
