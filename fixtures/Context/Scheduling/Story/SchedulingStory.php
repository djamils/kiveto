<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Scheduling\Story;

use Zenstruck\Foundry\Story;

final class SchedulingStory extends Story
{
    public function build(): void
    {
        // Note: This story assumes clinics, users, owners, and animals exist from other BC
        // fixtures (AccessControl, Clinic, Client, Animal). Inject the appropriate factories
        // when actual fixture data is needed.
    }
}
