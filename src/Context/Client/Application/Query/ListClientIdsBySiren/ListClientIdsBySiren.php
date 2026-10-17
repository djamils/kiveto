<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientIdsBySiren;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The clients of the clinic carrying this SIREN, archived ones included.
 * Several establishments share a SIREN, so a match is only a warning.
 */
final readonly class ListClientIdsBySiren implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $siren,
    ) {
    }
}
