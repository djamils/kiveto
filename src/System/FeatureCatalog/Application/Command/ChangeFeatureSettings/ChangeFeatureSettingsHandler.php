<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Command\ChangeFeatureSettings;

use App\Shared\Domain\Time\ClockInterface;
use App\System\FeatureCatalog\Application\Service\FeatureStates;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureRepositoryInterface;
use App\System\FeatureCatalog\Domain\FeatureStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangeFeatureSettingsHandler
{
    public function __construct(
        private FeatureRepositoryInterface $features,
        private FeatureStates $states,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws \DomainException an unknown feature or status, or an upcoming feature
     *
     * @return list<string>
     */
    public function __invoke(ChangeFeatureSettings $command): array
    {
        $settings = $this->states->settings(FeatureCatalogue::get($command->code));
        $status   = FeatureStatus::tryFrom($command->status) ?? throw new \DomainException('Choisis un statut.');
        $changes  = $settings->change($status, $command->defaultOn, $this->clock->now());

        if ([] !== $changes) {
            $this->features->saveSettings($settings);
        }

        return $changes;
    }
}
