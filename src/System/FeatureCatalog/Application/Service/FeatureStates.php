<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Service;

use App\System\FeatureCatalog\Domain\ClinicFeature;
use App\System\FeatureCatalog\Domain\FeatureDefinition;
use App\System\FeatureCatalog\Domain\FeatureRepositoryInterface;
use App\System\FeatureCatalog\Domain\FeatureSettings;

/**
 * Reads what is in force: the saved settings of a feature (else its
 * initial ones) and whether a clinic has it.
 */
final readonly class FeatureStates
{
    public function __construct(
        private FeatureRepositoryInterface $features,
    ) {
    }

    public function settings(FeatureDefinition $definition): FeatureSettings
    {
        return $this->features->settings($definition->code) ?? FeatureSettings::initial($definition);
    }

    /**
     * An upcoming feature is off everywhere; a clinic that never had it set
     * keeps the legacy state.
     */
    public static function isOn(FeatureDefinition $definition, FeatureSettings $settings, ?ClinicFeature $clinic): bool
    {
        if ($settings->isUpcoming()) {
            return false;
        }

        return null !== $clinic ? $clinic->enabled : $definition->legacyOn;
    }
}
