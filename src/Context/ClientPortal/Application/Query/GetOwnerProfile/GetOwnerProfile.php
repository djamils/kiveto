<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerProfile;

use App\Shared\Application\Bus\QueryInterface;

/** The Portal profile of an owner account. Answers ?OwnerProfileDetails (null before any acceptance). */
final readonly class GetOwnerProfile implements QueryInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
