<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\FindInvoiceForAdmission;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The invoice raised for one visit, if the counter has been through it. Returns
 * null rather than throwing: not being invoiced yet is an ordinary state.
 */
final readonly class FindInvoiceForAdmission implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $admissionId,
    ) {
    }
}
