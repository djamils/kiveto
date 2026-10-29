<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ClaimPortalIdentity;

final readonly class ClaimOutcome
{
    /**
     * @param string|null $userId the owner account, when the proof holds (null
     *                            when `createIfMissing` is false and the phone
     *                            has no account)
     */
    public function __construct(
        public ClaimResult $result,
        public ?string $userId = null,
    ) {
    }
}
