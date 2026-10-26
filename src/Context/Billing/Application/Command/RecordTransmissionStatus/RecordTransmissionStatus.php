<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RecordTransmissionStatus;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RecordTransmissionStatus implements CommandInterface
{
    public function __construct(
        public string $documentId,
        public int $sequence,
        public string $state,
        public ?string $blockReason,
        public ?int $statusCode,
        public ?string $statusLabel,
        public ?string $reasonCode,
        public ?string $reasonText,
        public bool $requiresAction,
        public string $occurredAt,
    ) {
    }
}
