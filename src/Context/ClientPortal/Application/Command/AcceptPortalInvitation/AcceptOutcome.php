<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\AcceptPortalInvitation;

final readonly class AcceptOutcome
{
    /**
     * @param string|null $userId the owner account, set only when ACCEPTED
     */
    public function __construct(
        public AcceptResult $result,
        public ?string $userId = null,
    ) {
    }
}
