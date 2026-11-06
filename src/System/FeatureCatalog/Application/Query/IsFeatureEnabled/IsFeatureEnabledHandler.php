<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Query\IsFeatureEnabled;

use App\System\FeatureCatalog\Application\Service\FeatureResolver;
use App\System\FeatureCatalog\Domain\FeatureCatalogue;
use App\System\FeatureCatalog\Domain\UnknownFeatureException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IsFeatureEnabledHandler
{
    public function __construct(
        private FeatureResolver $resolver,
    ) {
    }

    public function __invoke(IsFeatureEnabled $query): bool
    {
        try {
            $definition = FeatureCatalogue::get($query->code);
        } catch (UnknownFeatureException) {
            return false;
        }

        return $this->resolver->decisionFor($definition, $query->clinicId)->isOn();
    }
}
