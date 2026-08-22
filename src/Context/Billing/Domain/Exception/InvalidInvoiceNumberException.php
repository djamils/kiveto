<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Exception;

final class InvalidInvoiceNumberException extends \DomainException
{
    public function __construct(string $value)
    {
        parent::__construct(\sprintf('Invalid invoice number: "%s". Expected FAC-YYYY-NNNNNN.', $value));
    }
}
