<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\CheckClinicSlug;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Can a new clinic take this tenant id?
 */
final readonly class CheckClinicSlug implements QueryInterface
{
    public function __construct(
        public string $slug,
    ) {
    }
}
