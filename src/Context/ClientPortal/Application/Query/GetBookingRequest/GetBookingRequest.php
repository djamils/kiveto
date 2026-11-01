<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetBookingRequest;

use App\Shared\Application\Bus\QueryInterface;

/**
 * One « Nouveau client » request of the clinic, any status. Answers
 * ?ClinicBookingRequest: null for an unknown id or another clinic's.
 */
final readonly class GetBookingRequest implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $requestId,
    ) {
    }
}
