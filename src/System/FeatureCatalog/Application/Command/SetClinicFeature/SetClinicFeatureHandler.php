<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Command\SetClinicFeature;

use App\Shared\Domain\Time\ClockInterface;
use App\System\FeatureCatalog\Application\Service\FeatureResolver;
use App\System\FeatureCatalog\Domain\ClinicFeature;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SetClinicFeatureHandler
{
    public function __construct(
        private FeatureRepositoryInterface $features,
        private FeatureResolver $resolver,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Records the clinic's own setting. While the feature is cut off or kept
     * for another plan, the setting waits: it decides again once they end.
     *
     * @throws \DomainException an unknown or upcoming feature
     *
     * @return bool whether the clinic's setting changed
     */
    public function __invoke(SetClinicFeature $command): bool
    {
        $definition = FeatureCatalogue::get($command->code);

        if ($this->resolver->settings($definition)->isUpcoming()) {
            throw new \DomainException('Une fonctionnalité « À venir » ne s’active pas : son module n’existe pas encore.');
        }

        $current = $this->features->clinicFeature($command->clinicId, $command->code);
        $wasOn   = null !== $current ? $current->enabled : $definition->legacyOn;

        if (null !== $current && $current->enabled === $command->enabled) {
            return false;
        }

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
