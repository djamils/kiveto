<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\ListOutstandingInvoicesOfPayers;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The invoices several clients still have to pay, at once: a page listing
 * many owners (the « Flux ambulatoire » desk) reads them in one query instead
 * of one search per owner. Answers `array<string, list<string>>`: invoice ids
 * by payer, the oldest first; a payer who owes nothing is left out.
 */
final readonly class ListOutstandingInvoicesOfPayers implements QueryInterface
{
    /**
     * @param list<string> $payerClientIds UUID strings
     */
    public function __construct(
        public string $clinicId,
        public array $payerClientIds,
    ) {
    }
}
