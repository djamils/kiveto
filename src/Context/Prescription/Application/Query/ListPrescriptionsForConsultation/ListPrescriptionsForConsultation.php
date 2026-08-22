<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Which ordonnances came out of one consultation — what the cockpit needs to
 * know before offering to print them.
 */
final readonly class ListPrescriptionsForConsultation implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $consultationId,
    ) {
    }
}
