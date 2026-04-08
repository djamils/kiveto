<?php

declare(strict_types=1);

namespace App\Fixtures\System\IdentityAccess\Story;

use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use Zenstruck\Foundry\Story;

final class ClinicVetStory extends Story
{
    public function build(): void
    {
        ClinicUserFactory::new()
            ->withEmail('vet@kiveto.local')
            ->withPlainPassword('vet')
            ->create()
        ;
    }
}
