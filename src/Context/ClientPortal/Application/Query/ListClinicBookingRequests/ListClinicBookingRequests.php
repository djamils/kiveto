<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListClinicBookingRequests;

use App\Shared\Application\Bus\QueryInterface;

/**
 * VetApp « Demandes Portal », type « Nouveau client »: the clinic's pending
 * new-client booking requests, oldest first — or, with `decided`, its
 * confirmed and declined ones, the latest answered first (the most recent
 * 500). Answers list<ClinicBookingRequest>.
 */
final readonly class ListClinicBookingRequests implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public bool $decided = false,
    ) {
    }
}
