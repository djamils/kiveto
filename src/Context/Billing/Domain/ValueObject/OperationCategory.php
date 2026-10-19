<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

/**
 * Whether a line supplies goods or services, as its tax category says.
 *
 * Frozen on the line at creation, like the VAT rate: it decides the invoicing
 * framework of the document (EN16931 BT-23 — goods, services or mixed).
 */
enum OperationCategory: string
{
    case GOODS    = 'goods';
    case SERVICES = 'services';
}
