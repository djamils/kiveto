<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Query;

/**
 * Whether one clinic has one feature and what decided it
 * (FeatureDecision: 'set_on', 'cut_off', 'outside_audience'…), and when
 * the clinic's own setting changed, by whom (null for a clinic that keeps
 * the legacy state). `own` tells whether the clinic's switch decides.
 */
final readonly class ClinicFeatureView
{
    public function __construct(
        public string $clinicId,
        public string $code,
        public bool $enabled,
        public ?\DateTimeImmutable $changedAt,
        public ?string $changedByName,
        public string $decision,
        public string $decisionLabel,
        public bool $own,
    ) {
    }
}
