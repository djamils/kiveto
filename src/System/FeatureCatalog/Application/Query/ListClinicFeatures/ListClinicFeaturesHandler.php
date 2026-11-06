<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Query\ListClinicFeatures;

use App\System\FeatureCatalog\Application\Query\ClinicFeatureView;
use App\System\FeatureCatalog\Application\Service\FeatureResolver;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureDefinition;
use App\System\FeatureCatalog\Domain\FeatureRepositoryInterface;
use App\System\FeatureCatalog\Domain\FeatureSettings;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClinicFeaturesHandler
{
    public function __construct(
        private FeatureRepositoryInterface $features,
        private FeatureResolver $resolver,
    ) {
    }

    /**
     * @return array<string, array<string, ClinicFeatureView>>
     */
    public function __invoke(ListClinicFeatures $query): array
    {
        $definitions = FeatureCatalogue::all();
        $settings    = array_map($this->resolver->settings(...), $definitions);
        $plans       = $this->resolver->plansFor($settings, $query->clinicIds);
        $answer      = [];

        foreach ($definitions as $index => $definition) {
            $answer[$definition->code] = $this->states($definition, $settings[$index], $query->clinicIds, $plans);
        }

        return $answer;
    }

    /**
     * @param list<string>          $clinicIds
     * @param array<string, string> $plans
     *
     * @return array<string, ClinicFeatureView>
     */
    private function states(FeatureDefinition $definition, FeatureSettings $settings, array $clinicIds, array $plans): array
    {
        $set = [];
        foreach ($this->features->clinicsOf($definition->code) as $clinic) {
            $set[$clinic->clinicId] = $clinic;
        }

        $states = [];
        foreach ($clinicIds as $clinicId) {
            $row      = $set[$clinicId] ?? null;
            $decision = FeatureResolver::decide($definition, $settings, $row, $plans[$clinicId] ?? null);

            $states[$clinicId] = new ClinicFeatureView(
                clinicId: $clinicId,
                code: $definition->code,
                enabled: $decision->isOn(),
                changedAt: $row?->changedAt,
                changedByName: $row?->changedByName,
                decision: $decision->value,
                decisionLabel: $decision->label(),
                own: $decision->isTheClinicsOwn(),
            );
        }

        return $states;
    }
}
