<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Query\ListClinicFeatures;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The state of every feature for the clinics given, keyed by code then by
 * clinic id (F2 « Cliniques », the clinic's features card).
 */
final readonly class ListClinicFeatures implements QueryInterface
{
    /**
     * @param list<string> $clinicIds
     */
    public function __construct(
        public array $clinicIds,
    ) {
    }
}
