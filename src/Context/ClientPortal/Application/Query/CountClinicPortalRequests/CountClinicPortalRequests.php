<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\CountClinicPortalRequests;

use App\Shared\Application\Bus\QueryInterface;

/**
 * VetApp « Demandes Portal » badge: how many requests wait for the clinic's
 * answer, of every type, counted without reading the requests themselves.
 * Answers an int.
 */
final readonly class CountClinicPortalRequests implements QueryInterface
{
    public function __construct(
        public string $clinicId,
    ) {
    }
}
