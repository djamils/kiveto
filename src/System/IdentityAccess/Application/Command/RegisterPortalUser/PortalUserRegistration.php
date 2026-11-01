<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RegisterPortalUser;

final readonly class PortalUserRegistration
{
    /**
     * @param string|null $userId the owner account, when REGISTERED or REPLAYED
     */
    public function __construct(
        public RegistrationResult $result,
        public ?string $userId = null,
    ) {
    }
}
