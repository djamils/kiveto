<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Query\GetClinicListing;

use App\Shared\Application\Bus\QueryInterface;

/**
 * One clinic of the directory by its listing id → ?ClinicListingDetail: null
 * when unknown, malformed, not listed or inactive (the API's 404 on every
 * `/clinic-listings/{l}…` route).
 */
final readonly class GetClinicListing implements QueryInterface
{
    public function __construct(public string $listingId)
    {
    }
}
