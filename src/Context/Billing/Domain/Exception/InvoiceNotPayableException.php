<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Exception;

use App\Context\Billing\Domain\ValueObject\InvoiceStatus;

final class InvoiceNotPayableException extends \DomainException
{
    public function __construct(string $invoiceId, InvoiceStatus $status)
    {
        parent::__construct(\sprintf(
            'Invoice %s cannot take a payment while it is %s.',
            $invoiceId,
            $status->value,
        ));
    }
}
