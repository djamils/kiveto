<?php

declare(strict_types=1);

namespace App\Animal\Application\Query\GetAnimalById;

final readonly class TransferDto
{
    public function __construct(
        public string $transferStatus,
        public ?string $soldAt,
        public ?string $givenAt,
    ) {
    }
}
