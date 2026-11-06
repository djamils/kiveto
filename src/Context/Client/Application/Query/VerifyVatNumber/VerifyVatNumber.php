<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\VerifyVatNumber;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Is this intra-community VAT number registered (VIES)? Used by the console
 * when it creates a clinic from its SIREN.
 */
final readonly class VerifyVatNumber implements QueryInterface
{
    public function __construct(
        public string $vatNumber,
    ) {
    }
}
