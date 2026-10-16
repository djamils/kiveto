<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\SuggestPostalAddresses;

use App\Shared\Application\Bus\QueryInterface;

/**
 * « Tapez le début » of a client's address: the known addresses it may be,
 * those near the clinic (`nearPostalCode`) first.
 */
final readonly class SuggestPostalAddresses implements QueryInterface
{
    public const int MIN_LENGTH = 3;
    public const int MAX_LIMIT  = 10;

    public function __construct(
        public string $query,
        public int $limit = 6,
        public ?string $nearPostalCode = null,
    ) {
    }
}
