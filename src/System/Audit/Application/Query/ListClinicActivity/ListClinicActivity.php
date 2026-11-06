<?php

declare(strict_types=1);

namespace App\System\Audit\Application\Query\ListClinicActivity;

use App\Shared\Application\Bus\QueryInterface;

/**
 * « Activité récente » of a clinic's sheet.
 */
final readonly class ListClinicActivity implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public int $limit = 5,
    ) {
    }
}
