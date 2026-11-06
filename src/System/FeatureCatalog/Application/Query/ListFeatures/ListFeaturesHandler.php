<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Query\ListFeatures;

use App\System\FeatureCatalog\Application\Query\FeatureView;
use App\System\FeatureCatalog\Application\Service\FeatureStates;
use App\System\FeatureCatalog\Domain\ClinicFeature;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListFeaturesHandler
{
    public function __construct(
        private FeatureRepositoryInterface $features,
        private FeatureStates $states,
    ) {
    }

    /**
     * @return list<FeatureView>
     */
    public function __invoke(ListFeatures $query): array
    {
        $views = [];

        foreach (FeatureCatalogue::all() as $definition) {
            $settings = $this->states->settings($definition);
            $set      = [];
            foreach ($this->features->clinicsOf($definition->code) as $clinic) {
                $set[$clinic->clinicId] = $clinic;
            }

            $count = \count(array_filter($query->clinicIds, static fn (string $id): bool => FeatureStates::isOn($definition, $settings, $set[$id] ?? null)));
            $last  = array_reduce($set, static fn (?\DateTimeImmutable $at, ClinicFeature $c): \DateTimeImmutable => null === $at || $c->changedAt > $at ? $c->changedAt : $at, $settings->updatedAt());

            $views[] = new FeatureView(
                code: $definition->code,
                name: $definition->name,
                description: $definition->description,
                effect: $definition->effect,
                status: $settings->status()->value,
                statusLabel: $settings->status()->label(),
                defaultOn: $settings->isDefaultOn(),
                enabledCount: $count,
                updatedAt: $last,
            );
        }

        return $views;
    }
}
