<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\TrustDevice;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Faire confiance à cet appareil 30 jours »: answers the secret of the
 * cookie, in clear, set once.
 */
final readonly class TrustDevice implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $label,
    ) {
    }
}
