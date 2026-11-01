<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Command\AttachListingCoordinates;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The geocoder's answer for the address of that hash: both coordinates, or
 * neither when it could not place the address.
 */
final readonly class AttachListingCoordinates implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $addressHash,
        public ?float $latitude,
        public ?float $longitude,
    ) {
    }
}
