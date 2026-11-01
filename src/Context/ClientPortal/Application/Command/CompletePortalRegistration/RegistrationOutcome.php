<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\CompletePortalRegistration;

final readonly class RegistrationOutcome
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
