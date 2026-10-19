<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

/**
 * The case of the invoicing framework (BT-23): 1 — an invoice left to pay;
 * 2 — an invoice already paid when issued. Cases 4 and 7 are not produced.
 */
enum FrameworkCase: string
{
    case TO_PAY       = '1';
    case ALREADY_PAID = '2';
}
