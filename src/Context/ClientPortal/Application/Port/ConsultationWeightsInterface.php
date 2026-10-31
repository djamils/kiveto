<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** Weights measured during consultations (Consultation context). */
interface ConsultationWeightsInterface
{
    /** The weight of the animal's latest CLOSED consultation, or null; open ones still change. */
    public function latestClosedOf(string $clinicId, string $animalId): ?VisitWeight;
}
