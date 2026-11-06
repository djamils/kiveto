<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Command\RestoreFeature;

use App\Shared\Domain\Time\ClockInterface;
use App\System\FeatureCatalog\Application\Service\FeatureResolver;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RestoreFeatureHandler
{
    public function __construct(
        private FeatureRepositoryInterface $features,
        private FeatureResolver $resolver,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws \DomainException an unknown feature, or one that is not cut off
     */
    public function __invoke(RestoreFeature $command): void
    {
        $settings = $this->resolver->settings(FeatureCatalogue::get($command->code));
        $settings->restore($this->clock->now());
        $this->features->saveSettings($settings);
    }
}
