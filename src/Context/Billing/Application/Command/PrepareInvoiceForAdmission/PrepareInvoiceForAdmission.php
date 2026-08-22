<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\PrepareInvoiceForAdmission;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Opens — or reopens — the invoice of a visit, returning its id.
 */
final readonly class PrepareInvoiceForAdmission implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $admissionId,
    ) {
    }
}
