<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Domain\Exception;

/**
 * A directory setting out of bounds: the field it is about and the French
 * reason, shown as is.
 */
final class InvalidListingSettingsException extends \DomainException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
