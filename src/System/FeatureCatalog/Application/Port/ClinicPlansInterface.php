<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Port;

/**
 * The plan of the clinics (the Clinic context's account), for the features
 * kept for one plan (FeatureAudience).
 */
interface ClinicPlansInterface
{
    /**
     * @param list<string> $clinicIds
     *
     * @return array<string, string> 'pilot' or 'paid' by clinic id; a clinic left out has no account yet
     */
    public function plansOf(array $clinicIds): array;
}
