<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France;

use App\System\PharmaceuticalRegistry\Application\Port\BlueprintBuilderInterface;
use App\System\PharmaceuticalRegistry\Domain\MarketingAuthorizationBlueprint;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto\AnmvMedicinalProductDto;

final readonly class AnmvBlueprintBuilder implements BlueprintBuilderInterface
{
    public function __construct(
        private AnmvCodeMapper $codeMapper,
    ) {
    }

    /** @param array<mixed> $rawDto */
    public function buildBlueprint(array $rawDto, ImportSource $source): MarketingAuthorizationBlueprint
    {
        /** @phpstan-ignore argument.type */
        $dto = AnmvMedicinalProductDto::fromArray($rawDto);

        return $this->codeMapper->buildBlueprint($dto);
    }
}
