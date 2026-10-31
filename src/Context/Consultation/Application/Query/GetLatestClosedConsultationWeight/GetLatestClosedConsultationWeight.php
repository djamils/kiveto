<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetLatestClosedConsultationWeight;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The weight recorded in the animal's latest closed consultation.
 *
 * Every patient record of the animal counts (reconciliation can leave several
 * behind). Open consultations are ignored: their vitals can still change. The
 * Portal compares it with the Animal weight records to show the latest one.
 */
final readonly class GetLatestClosedConsultationWeight implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $animalId,
    ) {
    }
}
