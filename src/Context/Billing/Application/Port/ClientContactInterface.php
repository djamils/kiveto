<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * How the payers of the clinic's invoices can be reached (Client's
 * notification contact, with the « Facturation » consent applied).
 */
interface ClientContactInterface
{
    public function contactOf(string $clinicId, string $clientId): ?ClientContact;

    /**
     * One read for a page of invoices; unknown clients are left out.
     *
     * @param list<string> $clientIds
     *
     * @return array<string, ClientContact> client id => contact
     */
    public function contactsOf(string $clinicId, array $clientIds): array;
}
