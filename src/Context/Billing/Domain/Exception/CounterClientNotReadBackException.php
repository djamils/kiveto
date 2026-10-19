<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Exception;

/**
 * A professional opened at the desk cannot be read back from its record: the
 * sale would go out under the contact's name instead of the legal name. An
 * inconsistency, not a desk mistake; the whole sale is rolled back.
 */
final class CounterClientNotReadBackException extends \RuntimeException
{
    public function __construct(public readonly string $clientId)
    {
        parent::__construct(\sprintf('The professional "%s" just opened cannot be read back.', $clientId));
    }
}
