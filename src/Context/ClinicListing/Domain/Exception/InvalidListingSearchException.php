<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Domain\Exception;

/**
 * A directory search the rules refuse (`q` too short, a coordinate out of
 * range or alone, a page size out of bounds): the field and the French reason.
 */
final class InvalidListingSearchException extends \DomainException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
