<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientById;

final readonly class ConsentDto
{
    /**
     * @param list<string> $channels
     */
    public function __construct(
        public string $kind,
        public array $channels,
        public string $grantedAt,
    ) {
    }
}
