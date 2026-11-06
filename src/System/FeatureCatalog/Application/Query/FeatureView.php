<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Query;

/**
 * A feature as the console lists it, with the clinics that have it among
 * those asked about; which clinics it is kept for; whether it is cut off
 * everywhere, why and by whom.
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
        public string $audience,
        public string $audienceLabel,
        public ?string $cutReason,
        public ?\DateTimeImmutable $cutAt,
        public ?string $cutByName,
    ) {
    }

    public function isUpcoming(): bool
    {
        return 'upcoming' === $this->status;
    }

    public function isCut(): bool
    {
        return null !== $this->cutAt;
    }
}
