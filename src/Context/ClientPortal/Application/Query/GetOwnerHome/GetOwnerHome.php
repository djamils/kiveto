<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerHome;

use App\Shared\Application\Bus\QueryInterface;

/** The home of an owner account in a clinic. Answers OwnerHome. */
final readonly class GetOwnerHome implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
    ) {
    }
}
