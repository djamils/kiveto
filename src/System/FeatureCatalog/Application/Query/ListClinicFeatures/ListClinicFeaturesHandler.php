<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Query\ListClinicFeatures;

use App\System\FeatureCatalog\Application\Query\ClinicFeatureView;
use App\System\FeatureCatalog\Application\Service\FeatureStates;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClinicFeaturesHandler
{
    public function __construct(
        private FeatureRepositoryInterface $features,
        private FeatureStates $states,
    ) {
    }

    /**
     * @return array<string, array<string, ClinicFeatureView>>
     */
    public function __invoke(ListClinicFeatures $query): array
    {
        $answer = [];

        foreach (FeatureCatalogue::all() as $definition) {
            $settings = $this->states->settings($definition);
            $set      = [];
            foreach ($this->features->clinicsOf($definition->code) as $clinic) {
                $set[$clinic->clinicId] = $clinic;
            }

            $answer[$definition->code] = [];
            foreach ($query->clinicIds as $clinicId) {
                $row                                  = $set[$clinicId] ?? null;
                $answer[$definition->code][$clinicId] = new ClinicFeatureView(
                    clinicId: $clinicId,
                    code: $definition->code,
                    enabled: FeatureStates::isOn($definition, $settings, $row),
                    changedAt: $row?->changedAt,
                    changedByName: $row?->changedByName,
                );
            }
        }

        return $answer;
    }
}
