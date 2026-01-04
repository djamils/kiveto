<?php

namespace App\Fixtures\Story;

use App\Fixtures\IdentityAccess\Factory\BackofficeUserFactory;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

#[AsFixture(name: 'main')]
final class AppStory extends Story
{
    public function build(): void
    {
        // SomeFactory::createOne();
        BackofficeUserFactory::createOne();
    }
}
