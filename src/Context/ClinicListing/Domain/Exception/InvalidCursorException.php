<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Domain\Exception;

/**
 * A directory cursor that cannot be read, or that was issued for another
 * ordering or other coordinates than the request's.
 */
final class InvalidCursorException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('The cursor is invalid for this search.');
    }
}
