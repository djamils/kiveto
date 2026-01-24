<?php

declare(strict_types=1);

namespace App\Animal\Application\Query\GetAnimalById;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetAnimalById implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $animalId,
    ) {
    }
}
