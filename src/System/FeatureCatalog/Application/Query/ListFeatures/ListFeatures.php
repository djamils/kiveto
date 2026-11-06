<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Query\ListFeatures;

use App\Shared\Application\Bus\QueryInterface;

/**
 * F1 — every feature of the catalogue, counted over the clinics given (the
 * console passes the clinics it lists).
 */
final readonly class ListFeatures implements QueryInterface
{
    /**
     * @param list<string> $clinicIds
     */
    public function __construct(
        public array $clinicIds,
    ) {
    }
}
