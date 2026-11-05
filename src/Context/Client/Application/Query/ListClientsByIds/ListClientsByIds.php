<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientsByIds;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Several files of a clinic at once, as GetClientById reads one: a page
 * naming many owners reads them in two queries instead of two per owner.
 */
final readonly class ListClientsByIds implements QueryInterface
{
    /**
     * @param list<string> $clientIds UUID strings; duplicates are read once
     */
    public function __construct(
        public string $clinicId,
        public array $clientIds,
    ) {
    }
}
