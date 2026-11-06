<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Domain;

final class UnknownFeatureException extends \DomainException
{
    public function __construct(string $code)
    {
        parent::__construct(\sprintf('Fonctionnalité inconnue : « %s ».', $code));
    }
}
