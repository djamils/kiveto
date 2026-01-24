<?php

declare(strict_types=1);

namespace App\Animal\Application\Query\GetAnimalById;

final readonly class LifeCycleDto
{
    public function __construct(
        public string $lifeStatus,
        public ?string $deceasedAt,
        public ?string $missingSince,
    ) {
    }
}
