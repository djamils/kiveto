<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

/**
 * Global hourly budget of sign-in codes sent to phones Kiveto knows nothing
 * about (anti SMS-pumping). Beyond it, the challenge is created but no SMS
 * leaves, and an alert is raised once per window.
 */
interface UnknownNumberSmsBudgetInterface
{
    /** Spends one SMS of the budget; false when it is exhausted. */
    public function consume(): bool;
}
