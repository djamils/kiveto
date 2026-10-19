<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Exception;

/**
 * The tax profile typed at the desk for a new professional cannot open its
 * record: unknown status, no or too long a legal name, invalid SIREN or VAT
 * number.
 */
final class InvalidCounterClientTaxProfileException extends \DomainException
{
}
