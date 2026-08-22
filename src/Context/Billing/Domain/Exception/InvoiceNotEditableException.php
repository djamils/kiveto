<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Exception;

use App\Context\Billing\Domain\ValueObject\InvoiceStatus;

final class InvoiceNotEditableException extends \DomainException
{
    public function __construct(string $invoiceId, InvoiceStatus $status)
    {
        parent::__construct(\sprintf(
            'Invoice %s can no longer be edited: it is %s.',
            $invoiceId,
            $status->value,
        ));
    }
}
