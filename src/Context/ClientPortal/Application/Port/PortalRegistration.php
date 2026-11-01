<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

final readonly class PortalRegistration
{
    /**
     * @param string|null $userId the owner account, when REGISTERED or REPLAYED
     */
    public function __construct(
        public PortalRegistrationResult $result,
        public ?string $userId = null,
    ) {
    }
}
