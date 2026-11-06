<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RegenerateBackupCodes;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Régénérer » the backup codes: answers the ten new ones in clear.
 */
final readonly class RegenerateBackupCodes implements CommandInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
