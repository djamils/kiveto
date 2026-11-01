<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListClinicBookingRequests;

use App\Shared\Application\Bus\QueryInterface;

/**
 * VetApp « Demandes Portal », type « Nouveau client »: the clinic's pending
 * new-client booking requests, oldest first. Answers list<ClinicBookingRequest>.
 */
final readonly class ListClinicBookingRequests implements QueryInterface
{
    public function __construct(
        public string $clinicId,
    ) {
    }
}
