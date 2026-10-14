<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Exception;

final class CreditExceedsInvoiceException extends \DomainException
{
    public function __construct(int $amountMinorUnits, int $creditableMinorUnits)
    {
        parent::__construct(\sprintf(
            'A credit of %d cannot be applied: %d is left to credit on this invoice.',
            $amountMinorUnits,
            $creditableMinorUnits,
        ));
    }
}
