<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RevokeTrustedDevices;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Tout révoquer »: every trusted browser of the account asks the code
 * again. Answers how many were trusted.
 */
final readonly class RevokeTrustedDevices implements CommandInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
