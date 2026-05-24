<?php

declare(strict_types=1);

namespace App\Shared\Domain\UnitOfMeasure\ValueObject;

final readonly class UnitDefinition
{
    public function __construct(
        public UnitOfMeasure $code,
        public UnitCategory $category,
        public ?string $baseFactor,
    ) {
    }
}
