<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Domain;

interface FeatureRepositoryInterface
{
    /** The saved settings of a feature; null when never saved. */
    public function settings(string $code): ?FeatureSettings;

    public function saveSettings(FeatureSettings $settings): void;

    public function clinicFeature(string $clinicId, string $code): ?ClinicFeature;

    public function saveClinicFeature(ClinicFeature $feature): void;

    /**
     * The clinics that have this feature set, either way.
     *
     * @return list<ClinicFeature>
     */
    public function clinicsOf(string $code): array;

    /**
     * The features this clinic has set, either way.
     *
     * @return list<ClinicFeature>
     */
    public function featuresOf(string $clinicId): array;
}
