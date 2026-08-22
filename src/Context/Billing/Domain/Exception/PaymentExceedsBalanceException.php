<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Exception;

final class PaymentExceedsBalanceException extends \DomainException
{
    public function __construct(int $amountMinorUnits, int $balanceMinorUnits)
    {
        parent::__construct(\sprintf(
            'Payment of %d exceeds the balance due of %d.',
            $amountMinorUnits,
            $balanceMinorUnits,
        ));
    }
}
