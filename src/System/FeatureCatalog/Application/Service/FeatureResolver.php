<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Service;

use App\System\FeatureCatalog\Application\Port\ClinicPlansInterface;
use App\System\FeatureCatalog\Domain\ClinicFeature;
use App\System\FeatureCatalog\Domain\FeatureAudience;
use App\System\FeatureCatalog\Domain\FeatureDefinition;
use App\System\FeatureCatalog\Domain\FeatureRepositoryInterface;
use App\System\FeatureCatalog\Domain\FeatureSettings;

/**
 * The one place that decides whether a clinic has a feature, in this order:
 *
 * 1. « À venir »: off everywhere, its module does not exist yet;
 * 2. cut off everywhere (the kill switch): off, whatever else says;
 * 3. kept for a plan the clinic is not on (« Réservée à »): off;
 * 4. what was set for the clinic (the console, the starting features);
 * 5. else the state the clinic had before the catalogue (`legacyOn`).
 *
 * The plans are read only for a feature kept for one plan.
 */
final readonly class FeatureResolver
{
    public function __construct(
        private FeatureRepositoryInterface $features,
        private ClinicPlansInterface $plans,
    ) {
    }

    /** The saved settings of a feature, else its initial ones. */
    public function settings(FeatureDefinition $definition): FeatureSettings
    {
        return $this->features->settings($definition->code) ?? FeatureSettings::initial($definition);
    }

    public function decisionFor(FeatureDefinition $definition, string $clinicId): FeatureDecision
    {
        $settings = $this->settings($definition);
        $plan     = FeatureAudience::ALL === $settings->audience() ? null : ($this->plans->plansOf([$clinicId])[$clinicId] ?? null);

        return self::decide($definition, $settings, $this->features->clinicFeature($clinicId, $definition->code), $plan);
    }

    /**
     * The plans of the clinics, read only when one of these settings keeps a
     * feature for one plan.
     *
     * @param list<FeatureSettings> $settings
     * @param list<string>          $clinicIds
     *
     * @return array<string, string>
     */
    public function plansFor(array $settings, array $clinicIds): array
    {
        foreach ($settings as $one) {
            if (FeatureAudience::ALL !== $one->audience()) {
                return [] === $clinicIds ? [] : $this->plans->plansOf($clinicIds);
            }
        }

        return [];
    }

    public static function decide(FeatureDefinition $definition, FeatureSettings $settings, ?ClinicFeature $clinic, ?string $plan): FeatureDecision
    {
        return match (true) {
            $settings->isUpcoming()               => FeatureDecision::UPCOMING,
            null !== $settings->cutOff()          => FeatureDecision::CUT_OFF,
            !$settings->audience()->admits($plan) => FeatureDecision::OUTSIDE_AUDIENCE,
            null !== $clinic                      => $clinic->enabled ? FeatureDecision::SET_ON : FeatureDecision::SET_OFF,
            default                               => $definition->legacyOn ? FeatureDecision::LEGACY_ON : FeatureDecision::LEGACY_OFF,
        };
    }
}
