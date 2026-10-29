<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

final readonly class IdentityClaim
{
    /**
     * @param string|null $userId the owner account when the proof holds (null
     *                            without account in the no-create mode)
     */
    public function __construct(
        public IdentityClaimResult $result,
        public ?string $userId = null,
    ) {
    }
}
