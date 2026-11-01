<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Query\GetListingSettings;

use App\Shared\Application\Bus\QueryInterface;

/** The clinic's directory settings → ListingSettingsView (the defaults when never saved). */
final readonly class GetListingSettings implements QueryInterface
{
    public function __construct(public string $clinicId)
    {
    }
}
