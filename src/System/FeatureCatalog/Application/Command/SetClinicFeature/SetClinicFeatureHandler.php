<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Command\SetClinicFeature;

use App\Shared\Domain\Time\ClockInterface;
use App\System\FeatureCatalog\Application\Service\FeatureStates;
use App\System\FeatureCatalog\Domain\ClinicFeature;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SetClinicFeatureHandler
{
    public function __construct(
        private FeatureRepositoryInterface $features,
        private FeatureStates $states,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws \DomainException an unknown or upcoming feature
     */
    public function __invoke(SetClinicFeature $command): bool
    {
        $definition = FeatureCatalogue::get($command->code);
        $settings   = $this->states->settings($definition);

        if ($settings->isUpcoming()) {
            throw new \DomainException('Une fonctionnalité « À venir » ne s’active pas : son module n’existe pas encore.');
        }

        $current = $this->features->clinicFeature($command->clinicId, $command->code);

        if (null !== $current && $current->enabled === $command->enabled) {
            return false;
        }

        $wasOn = FeatureStates::isOn($definition, $settings, $current);

        $this->features->saveClinicFeature(new ClinicFeature(
            clinicId: $command->clinicId,
            code: $command->code,
            enabled: $command->enabled,
            changedAt: $this->clock->now(),
            changedById: $command->actorId,
            changedByName: $command->actorName,
        ));

        return $wasOn !== $command->enabled;
    }
}
