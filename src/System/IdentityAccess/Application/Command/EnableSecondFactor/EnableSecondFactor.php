<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\EnableSecondFactor;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The admin scanned `secret` (base32) and typed a code of his app: the
 * factor is enabled (a previous one replaced). Answers the ten backup codes
 * in clear, shown once.
 */
final readonly class EnableSecondFactor implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $secret,
        public string $code,
    ) {
    }
}
