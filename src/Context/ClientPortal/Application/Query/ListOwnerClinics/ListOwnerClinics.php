<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListOwnerClinics;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The clinics an owner account reaches through confirmed links or waits
 * for through pending ones (accepted link suggestions), one entry per
 * clinic. Answers list<OwnerClinic>.
 */
final readonly class ListOwnerClinics implements QueryInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
