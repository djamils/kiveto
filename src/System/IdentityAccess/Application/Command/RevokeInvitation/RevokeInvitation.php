<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RevokeInvitation;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The member's access to a clinic was disabled or ended: the invitation
 * links of that clinic stop working (other clinics' links are untouched).
 * The handler returns how many links were revoked.
 */
final readonly class RevokeInvitation implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
    ) {
    }
}
