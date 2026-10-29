<?php

declare(strict_types=1);

namespace App\Fixtures\System\IdentityAccess\Story;

use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use Zenstruck\Foundry\Story;

/**
 * Demo owner accounts. They sign in on the Portal API with their phone: the
 * one-time code is written to var/log/dev.log (channel dev_sms) by the
 * worker that sends the SMS.
 */
final class PortalUserStory extends Story
{
    /** Demo owner without e-mail. */
    public const string DEMO_PHONE = '+33612345678';

    /** Demo owner whose e-mail was copied from a clinic's client file. */
    public const string DEMO_PHONE_WITH_EMAIL = '+33612345679';

    public function build(): void
    {
        PortalUserFactory::new()->withPhone(self::DEMO_PHONE)->create();

        PortalUserFactory::new()
            ->withPhone(self::DEMO_PHONE_WITH_EMAIL)
            ->withEmail('proprietaire@kiveto.local')
            ->create()
        ;
    }
}
