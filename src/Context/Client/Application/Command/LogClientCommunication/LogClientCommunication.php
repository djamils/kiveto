<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\LogClientCommunication;

final readonly class LogClientCommunication
{
    public function __construct(
        public string $clinicId,
        public string $clientId,
        public string $channel,
        public string $direction,
        public string $subject,
        public ?string $body,
        public string $authorName,
    ) {
    }
}
