<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Domain;

use App\Shared\Domain\Identifier\AbstractUuidId;

/**
 * The directory's own identifier of a listing: never the clinic's id, so a
 * public URL of the directory reveals nothing the clinic's tenancy keys on.
 */
final class ClinicListingId extends AbstractUuidId
{
    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
