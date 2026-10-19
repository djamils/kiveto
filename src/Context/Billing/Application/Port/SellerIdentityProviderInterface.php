<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

use App\Context\Billing\Domain\ValueObject\SellerIdentity;

interface SellerIdentityProviderInterface
{
    /**
     * The clinic as the seller of its invoices. A clinic the Clinic context
     * does not know comes back empty: readiness then reports what is missing.
     */
    public function forClinic(string $clinicId): SellerIdentity;
}
