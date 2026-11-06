<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

use App\System\IdentityAccess\Domain\SecondFactor\TotpSecret;

/**
 * Randomness of the second factor, behind a port so that tests choose it.
 */
interface SecondFactorSecretsInterface
{
    public function newTotpSecret(): TotpSecret;

    /**
     * A backup code as printed: ten characters of an unambiguous alphabet,
     * grouped by five (« K7MQ2-XW9PD »).
     */
    public function newBackupCode(): string;
}
