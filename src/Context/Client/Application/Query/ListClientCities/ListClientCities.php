<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientCities;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The cities the clinic's clients actually live in, alphabetically.
 *
 * Feeds the city filter of the directory: the options are whatever the clinic
 * has on file rather than a fixed list.
 */
final readonly class ListClientCities implements QueryInterface
{
    public function __construct(
        public string $clinicId,
    ) {
    }
}
