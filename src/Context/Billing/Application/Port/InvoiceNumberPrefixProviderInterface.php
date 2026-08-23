<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * The prefix a clinic wants its invoice numbers to carry. Billing does not read
 * the Clinic tables to find it.
 */
interface InvoiceNumberPrefixProviderInterface
{
    /**
     * @return string|null null when the clinic has not chosen one
     */
    public function prefixFor(string $clinicId): ?string;
}
