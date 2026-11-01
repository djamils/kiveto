<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

/**
 * Hourly budgets of sign-in codes sent to phones Kiveto knows nothing about
 * (anti SMS-pumping), in three layers: per client network, per device hint,
 * and one global cap. Beyond any of them, the challenge is created but no
 * SMS leaves.
 */
interface UnknownNumberSmsBudgetInterface
{
    /** Spends one SMS of the network's budget (`a.b.c.0/24`, `x:x:x:x::/64`); false when exhausted. */
    public function consumeNetwork(string $networkKey): bool;

    /** Spends one SMS of the device hint's budget (`X-Device-Id`); false when exhausted. */
    public function consumeDevice(string $deviceId): bool;

    /**
     * Spends one SMS of the global budget; false when exhausted, with a
     * critical log once per window.
     */
    public function consumeGlobal(): bool;
}
