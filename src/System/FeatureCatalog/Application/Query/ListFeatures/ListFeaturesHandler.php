<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Query\ListFeatures;

use App\System\FeatureCatalog\Application\Query\FeatureView;
use App\System\FeatureCatalog\Application\Service\FeatureResolver;
use App\System\FeatureCatalog\Domain\ClinicFeature;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListFeaturesHandler
{
    public function __construct(
        private FeatureRepositoryInterface $features,
        private FeatureResolver $resolver,
    ) {
    }

    /**
     * @return list<FeatureView>
     */
    public function __invoke(ListFeatures $query): array
    {
        $definitions = FeatureCatalogue::all();
        $settings    = array_map($this->resolver->settings(...), $definitions);
        $plans       = $this->resolver->plansFor($settings, $query->clinicIds);
        $views       = [];

        foreach ($definitions as $index => $definition) {
            $one = $settings[$index];
            $set = [];
            foreach ($this->features->clinicsOf($definition->code) as $clinic) {
                $set[$clinic->clinicId] = $clinic;
            }

            $count = \count(array_filter(
                $query->clinicIds,
                static fn (string $id): bool => FeatureResolver::decide($definition, $one, $set[$id] ?? null, $plans[$id] ?? null)->isOn(),
            ));
            $last = array_reduce($set, static fn (?\DateTimeImmutable $at, ClinicFeature $c): \DateTimeImmutable => null === $at || $c->changedAt > $at ? $c->changedAt : $at, $one->updatedAt());
            $cut  = $one->cutOff();

            $views[] = new FeatureView(
                code: $definition->code,
                name: $definition->name,
                description: $definition->description,
                effect: $definition->effect,
                status: $one->status()->value,
                statusLabel: $one->status()->label(),
                defaultOn: $one->isDefaultOn(),
                enabledCount: $count,
                updatedAt: $last,
                audience: $one->audience()->value,
                audienceLabel: $one->audience()->label(),
                cutReason: $cut?->reason,
                cutAt: $cut?->at,
                cutByName: $cut?->byName,
            );
        }

        return $views;
    }
}
