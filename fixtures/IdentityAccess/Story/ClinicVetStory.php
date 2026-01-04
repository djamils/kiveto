<?php

declare(strict_types=1);

namespace App\Fixtures\IdentityAccess\Story;

use App\Fixtures\IdentityAccess\Factory\ClinicUserFactory;
use Zenstruck\Foundry\Story;

final class ClinicVetStory extends Story
{
    public function build(): void
    {
        ClinicUserFactory::new()
            ->withEmail('vet@local.test')
            ->withPlainPassword('vet')
            ->create();
    }
}
