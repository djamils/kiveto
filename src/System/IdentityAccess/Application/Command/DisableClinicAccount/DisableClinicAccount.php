<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\DisableClinicAccount;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Kiveto turns a clinic account off from the console: no sign-in any more
 * and every open session revoked. Its memberships stay, so turning it back
 * on gives the same access back. Answers how many sessions were revoked.
 */
final readonly class DisableClinicAccount implements CommandInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
