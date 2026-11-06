<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * « Facturation électronique » (the console's feature catalogue): off, a
 * professional document is not handed to the Approved Platform; it waits,
 * and the reconciliation sends it once the feature is on.
 */
interface ElectronicInvoicingFeatureInterface
{
    public function isOnFor(string $clinicId): bool;
}
