<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * Where a professional document stands on its way to the approved platform,
 * as the ElectronicInvoicing context last reported it.
 */
final readonly class TransmissionStatusUpdate
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
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
