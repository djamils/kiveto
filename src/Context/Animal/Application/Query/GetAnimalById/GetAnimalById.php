<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\GetAnimalById;

use App\Shared\Application\Bus\RequestCacheableQueryInterface;

/** Read by several blocks of a page (the patient, its Portal share): once for the request. */
final readonly class GetAnimalById implements RequestCacheableQueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $animalId,
    ) {
    }
}
