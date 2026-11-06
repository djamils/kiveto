<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Command\ApplyStartingFeatures;

use App\Shared\Domain\Time\ClockInterface;
use App\System\FeatureCatalog\Application\Service\FeatureStates;
use App\System\FeatureCatalog\Domain\ClinicFeature;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ApplyStartingFeaturesHandler
{
    public function __construct(
        private FeatureRepositoryInterface $features,
        private FeatureStates $states,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<string> the codes switched on
     */
    public function __invoke(ApplyStartingFeatures $command): array
    {
        $now = $this->clock->now();
        $on  = [];

        foreach (FeatureCatalogue::all() as $definition) {
            if ($this->states->settings($definition)->isUpcoming()) {
                continue;
            }

            $enabled = \in_array($definition->code, $command->enabledCodes, true);
            $this->features->saveClinicFeature(new ClinicFeature($command->clinicId, $definition->code, $enabled, $now, $command->actorId, $command->actorName));

            if ($enabled) {
                $on[] = $definition->code;
            }
        }

        return $on;
    }
}
