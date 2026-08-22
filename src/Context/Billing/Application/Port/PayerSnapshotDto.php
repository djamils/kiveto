<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

final readonly class PayerSnapshotDto
{
    /**
     * @param list<string> $addressLines
     */
    public function __construct(
        public ?string $clientId,
        public string $displayName,
        public array $addressLines = [],
    ) {
    }
}
