<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Query\IsFeatureEnabled;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Whether a clinic has a feature, for the modules it gates. An unknown code
 * answers false.
 */
final readonly class IsFeatureEnabled implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $code,
    ) {
    }
}
