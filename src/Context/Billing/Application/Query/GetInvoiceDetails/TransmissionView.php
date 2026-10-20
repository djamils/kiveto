<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetInvoiceDetails;

/**
 * Where a professional document stands on its way to the approved platform:
 * Billing's own `requested` / `request_failed`, or what ElectronicInvoicing
 * reported (pending_connection, queued, submitting, submitted, accepted,
 * inadmissible, blocked, not_required) with the latest lifecycle status.
 */
final readonly class TransmissionView
{
    public function __construct(
        public string $state,
        public ?string $blockReason,
        public ?int $statusCode,
        public ?string $statusLabel,
        public ?string $reasonCode,
        public ?string $reason,
        public bool $requiresAction,
    ) {
    }
}
