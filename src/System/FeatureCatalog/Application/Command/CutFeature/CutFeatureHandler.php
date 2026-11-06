<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Command\CutFeature;

use App\Shared\Domain\Time\ClockInterface;
use App\System\FeatureCatalog\Application\Service\FeatureResolver;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CutFeatureHandler
{
    public function __construct(
        private FeatureRepositoryInterface $features,
        private FeatureResolver $resolver,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws \DomainException an unknown or upcoming feature, one already cut off, no reason
     */
    public function __invoke(CutFeature $command): void
    {
        $settings = $this->resolver->settings(FeatureCatalogue::get($command->code));
        $settings->cut($command->reason, $command->actorName, $this->clock->now());
        $this->features->saveSettings($settings);
    }
}
