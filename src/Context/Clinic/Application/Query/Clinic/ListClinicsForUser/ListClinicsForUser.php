<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser;

use App\Shared\Application\Bus\RequestCacheableQueryInterface;

/** The member's clinics and roles: the shell and the page both read them, once for the request. */
final readonly class ListClinicsForUser implements RequestCacheableQueryInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
