<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListCommunesForPostalCode;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The communes a French postal code serves (13600: Ceyreste, La Ciotat), the
 * choices of the « Ville » of a client's address.
 */
final readonly class ListCommunesForPostalCode implements QueryInterface
{
    public function __construct(
        public string $postalCode,
    ) {
    }
}
