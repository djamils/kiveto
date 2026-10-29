<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

/**
 * Keyed digest of the one-time codes: 10⁶ possible codes make a plain hash
 * reversible, a keyed one is not without the server secret.
 */
interface OtpCodeHasherInterface
{
    public function hash(string $code): string;
}
