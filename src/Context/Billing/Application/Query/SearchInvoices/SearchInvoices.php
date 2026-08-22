<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\SearchInvoices;

use App\Shared\Application\Bus\QueryInterface;

final readonly class SearchInvoices implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public SearchInvoicesCriteria $criteria,
    ) {
    }
}
