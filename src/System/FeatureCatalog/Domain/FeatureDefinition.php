<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Domain;

/**
 * A feature as the code knows it: its code, its words, where it starts.
 * `legacyOn` is the state of a clinic that never had it set — a clinic
 * created before the catalogue keeps the modules it already had.
 */
final readonly class FeatureDefinition
{
    public function __construct(
        public string $code,
        public string $name,
        public string $description,
        public string $effect,
        public FeatureStatus $initialStatus,
        public bool $initialDefaultOn,
        public bool $legacyOn,
    ) {
    }
}
