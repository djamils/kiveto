<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Command\ChangeFeatureSettings;

use App\Shared\Application\Bus\CommandInterface;

/**
 * F2 « Réglages globaux »: the status of a feature, whether new clinics
 * start with it, which clinics it is kept for ('all', 'pilot', 'paid').
 * Answers what changed (French lines for the history).
 */
final readonly class ChangeFeatureSettings implements CommandInterface
{
    public function __construct(
        public string $code,
        public string $status,
        public bool $defaultOn,
        public string $audience,
    ) {
    }
}
