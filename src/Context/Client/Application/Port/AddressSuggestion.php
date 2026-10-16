<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Port;

/**
 * An address the national directory knows: « 12 Rue de Rome », 13001,
 * Marseille.
 */
final readonly class AddressSuggestion
{
    public function __construct(
        public string $street,
        public string $postalCode,
        public string $city,
    ) {
    }
}
