<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\AddClientNote;

final readonly class AddClientNote
{
    public function __construct(
        public string $clinicId,
        public string $clientId,
        public string $kind,
        public string $content,
        public string $authorName,
    ) {
    }
}
