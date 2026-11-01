<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Domain\Exception;

final class InvalidListingCoordinatesException extends \InvalidArgumentException
{
    public function __construct(float $latitude, float $longitude)
    {
        parent::__construct(\sprintf('Coordinates (%F, %F) are out of range.', $latitude, $longitude));
    }
}
