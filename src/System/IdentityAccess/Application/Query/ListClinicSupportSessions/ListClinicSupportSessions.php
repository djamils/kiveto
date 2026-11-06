<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\ListClinicSupportSessions;

use App\Shared\Application\Bus\QueryInterface;

/**
 * « Sessions support » of a clinic's sheet: the latest, newest first.
 */
final readonly class ListClinicSupportSessions implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public int $limit = 3,
    ) {
    }
}
