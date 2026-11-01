<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientConsents;

final readonly class ClientConsentView
{
    /**
     * @param list<string> $channels `sms` and / or `email`
     */
    public function __construct(
        public string $kind,
        public array $channels,
        public \DateTimeImmutable $grantedAt,
    ) {
    }
}
