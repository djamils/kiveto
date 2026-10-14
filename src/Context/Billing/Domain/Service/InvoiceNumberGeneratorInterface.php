<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Service;

use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;

/**
 * Hands out the next invoice number of a clinic for a given year.
 *
 * The sequence must be gap-free and must not hand the same number twice, which
 * makes this a persistence concern rather than something the aggregate can do
 * on its own.
 */
interface InvoiceNumberGeneratorInterface
{
    public function next(ClinicId $clinicId, int $year): InvoiceNumber;

    /**
     * The next credit note number (« AV-2026-000001 »): credit notes run in a
     * continuous series of their own, apart from the invoices.
     */
    public function nextCreditNote(ClinicId $clinicId, int $year): InvoiceNumber;
}
