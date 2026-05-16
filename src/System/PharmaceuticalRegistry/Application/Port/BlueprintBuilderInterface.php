<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Port;

use App\System\PharmaceuticalRegistry\Domain\MarketingAuthorizationBlueprint;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;

interface BlueprintBuilderInterface
{
    /**
     * @param array<mixed> $rawDto
     */
    public function buildBlueprint(array $rawDto, ImportSource $source): MarketingAuthorizationBlueprint;
}
