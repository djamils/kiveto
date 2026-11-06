<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\EnableClinicAccount;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Réactiver le compte »: ACTIVE again when it had chosen a password,
 * PENDING (waiting for an invitation) otherwise.
 */
final readonly class EnableClinicAccount implements CommandInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
