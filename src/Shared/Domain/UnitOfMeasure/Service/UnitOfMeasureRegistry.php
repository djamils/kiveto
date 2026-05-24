<?php

declare(strict_types=1);

namespace App\Shared\Domain\UnitOfMeasure\Service;

use App\Shared\Domain\UnitOfMeasure\Exception\UnknownUnitOfMeasureException;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitDefinition;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;

interface UnitOfMeasureRegistry
{
    /** @throws UnknownUnitOfMeasureException */
    public function resolve(UnitOfMeasure $unit): UnitDefinition;

    public function has(UnitOfMeasure $unit): bool;

    /** @return list<UnitDefinition> */
    public function all(): array;
}
