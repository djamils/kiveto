<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Support;

use App\System\FeatureCatalog\Domain\ClinicFeature;
use App\System\FeatureCatalog\Domain\FeatureRepositoryInterface;
use App\System\FeatureCatalog\Domain\FeatureSettings;

final class InMemoryFeatures implements FeatureRepositoryInterface
{
    /** @var array<string, FeatureSettings> by code */
    public array $settings = [];

    /** @var array<string, array<string, ClinicFeature>> by code, then clinic id */
    public array $clinics = [];

    /** @var list<string> codes whose settings were saved, in order */
    public array $savedSettings = [];

    /** @var list<ClinicFeature> clinic rows saved, in order */
    public array $savedClinicFeatures = [];

    public function settings(string $code): ?FeatureSettings
    {
        return $this->settings[$code] ?? null;
    }

    public function saveSettings(FeatureSettings $settings): void
    {
        $this->settings[$settings->code()] = $settings;
        $this->savedSettings[]             = $settings->code();
    }

    public function clinicFeature(string $clinicId, string $code): ?ClinicFeature
    {
        return $this->clinics[$code][$clinicId] ?? null;
    }

    public function saveClinicFeature(ClinicFeature $feature): void
    {
        $this->clinics[$feature->code][$feature->clinicId] = $feature;
        $this->savedClinicFeatures[]                       = $feature;
    }

    public function clinicsOf(string $code): array
    {
        return array_values($this->clinics[$code] ?? []);
    }

    public function featuresOf(string $clinicId): array
    {
        $features = [];
        foreach ($this->clinics as $byClinic) {
            if (isset($byClinic[$clinicId])) {
                $features[] = $byClinic[$clinicId];
            }
        }

        return $features;
    }

    /**
     * A clinic row set on `changedAt` (UTC).
     */
    public function set(string $clinicId, string $code, bool $enabled, string $changedAt = '2026-11-01 08:00:00', ?string $byName = 'Djamil'): ClinicFeature
    {
        $feature                         = new ClinicFeature($clinicId, $code, $enabled, new \DateTimeImmutable($changedAt, new \DateTimeZone('UTC')), null, $byName);
        $this->clinics[$code][$clinicId] = $feature;

        return $feature;
    }
}
