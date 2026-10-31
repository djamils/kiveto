<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\PublishInvoice;

use App\Shared\Application\Bus\CommandInterface;

/** An issued invoice, shown to its payer (never to a non-paying owner). */
final readonly class PublishInvoice implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $invoiceId,
    ) {
    }
}
