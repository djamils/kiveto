<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Support;

use App\System\FeatureCatalog\Application\Port\ClinicPlansInterface;

/**
 * The plans of the clinics, as a test sets them; counts the reads.
 */
final class FakeClinicPlans implements ClinicPlansInterface
{
    public int $reads = 0;

    /**
     * @param array<string, string> $plans
     */
    public function __construct(
        private array $plans = [],
    ) {
    }

    public function plansOf(array $clinicIds): array
    {
        ++$this->reads;

        return array_intersect_key($this->plans, array_flip($clinicIds));
    }
}
