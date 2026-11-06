<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Domain;

/**
 * A feature cut off everywhere (the kill switch): why, when, by whom. While
 * it holds, no clinic has the feature, whatever was set for it.
 */
final readonly class FeatureCutOff
{
    public function __construct(
        public string $reason,
        public \DateTimeImmutable $at,
        public ?string $byName,
    ) {
    }
}
