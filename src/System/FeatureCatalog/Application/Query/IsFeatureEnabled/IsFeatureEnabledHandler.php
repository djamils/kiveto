<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Query\IsFeatureEnabled;

use App\System\FeatureCatalog\Application\Service\FeatureStates;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\FeatureRepositoryInterface;
use App\System\FeatureCatalog\Domain\UnknownFeatureException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IsFeatureEnabledHandler
{
    public function __construct(
        private FeatureRepositoryInterface $features,
        private FeatureStates $states,
    ) {
    }

    public function __invoke(IsFeatureEnabled $query): bool
    {
        try {
            $definition = FeatureCatalogue::get($query->code);
        } catch (UnknownFeatureException) {
            return false;
        }

        return FeatureStates::isOn($definition, $this->states->settings($definition), $this->features->clinicFeature($query->clinicId, $query->code));
    }
}
