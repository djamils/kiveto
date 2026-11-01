<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\PurgePortalPhoneTraces;

/** What still identified the owner when its traces were purged. */
final readonly class PurgedPortalIdentity
{
    public function __construct(
        public ?string $phone,
        public ?string $email,
    ) {
    }
}
