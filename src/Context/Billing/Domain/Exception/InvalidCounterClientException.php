<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Exception;

/**
 * The contact details typed at the desk for a newcomer are not enough to
 * create their record.
 */
final class InvalidCounterClientException extends \DomainException
{
}
