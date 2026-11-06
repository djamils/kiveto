<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Console;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The clinics of the console (closed ones excluded); `search` narrows them
 * by name, tenant id, SIRET or legal name; `clinicId` reads one.
 */
final readonly class ListConsoleClinics implements QueryInterface
{
    public function __construct(
        public ?string $search = null,
        public ?string $clinicId = null,
        public int $limit = 500,
    ) {
    }
}
