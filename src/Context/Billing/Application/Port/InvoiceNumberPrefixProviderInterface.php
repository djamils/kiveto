<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * The prefixes a clinic wants its invoice and credit note numbers to carry. Billing does not read
 * the Clinic tables to find it.
 */
interface InvoiceNumberPrefixProviderInterface
{
    /**
     * @return string|null null when the clinic has not chosen one
     */
    public function prefixFor(string $clinicId): ?string;

    /**
     * The prefix of its credit notes.
     *
     * @return string|null null when the clinic has not chosen one
     */
    public function creditNotePrefixFor(string $clinicId): ?string;
}
