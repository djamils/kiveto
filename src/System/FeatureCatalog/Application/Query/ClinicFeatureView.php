<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Query;

/**
 * Whether one clinic has one feature, and since when, by whom (null for a
 * clinic that keeps the legacy state).
 */
final readonly class ClinicFeatureView
{
    public function __construct(
        public string $clinicId,
        public string $code,
        public bool $enabled,
        public ?\DateTimeImmutable $changedAt,
        public ?string $changedByName,
    ) {
    }
}
