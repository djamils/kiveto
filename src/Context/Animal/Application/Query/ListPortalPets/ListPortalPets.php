<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListPortalPets;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The pets a Portal caller may see: active animals whose active primary owner
 * is one of the given clients, never transferred (sold or given).
 */
final readonly class ListPortalPets implements QueryInterface
{
    /**
     * @param list<string> $clientIds UUID strings
     */
    public function __construct(
        public string $clinicId,
        public array $clientIds,
    ) {
    }
}
