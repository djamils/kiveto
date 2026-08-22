<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\IssueInvoice;

use App\Shared\Application\Bus\CommandInterface;

final readonly class IssueInvoice implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $invoiceId,
    ) {
    }
}
