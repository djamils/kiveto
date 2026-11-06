<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Query;

/**
 * A feature as the console lists it, with the clinics that have it among
 * those asked about.
 */
final readonly class FeatureView
{
    public function __construct(
        public string $code,
        public string $name,
        public string $description,
        public string $effect,
        public string $status,
        public string $statusLabel,
        public bool $defaultOn,
        public int $enabledCount,
        public ?\DateTimeImmutable $updatedAt,
    ) {
    }

    public function isUpcoming(): bool
    {
        return 'upcoming' === $this->status;
    }
}
