<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Domain;

/**
 * A feature switched on or off for one clinic, and who did it last.
 */
final readonly class ClinicFeature
{
    public function __construct(
        public string $clinicId,
        public string $code,
        public bool $enabled,
        public \DateTimeImmutable $changedAt,
        public ?string $changedById,
        public ?string $changedByName,
    ) {
    }
}
