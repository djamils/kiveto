<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Query\GetTransmissionStatus;

use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmission;

/**
 * Where a document stands on its way to the platform, as the issuing context
 * shows it. `state` is one of pending_connection, queued, awaiting_invoice,
 * submitting, submitted, accepted, inadmissible, blocked or not_required (an
 * internal credit note, `reasonCode` then being the status — 210 or 213 —
 * that voided its invoice); the status is the latest lifecycle status
 * reported, if any; `sequence` increases with every change.
 */
final readonly class TransmissionStatusView
{
    public function __construct(
        public string $clinicId,
        public string $documentId,
        public int $sequence,
        public string $state,
        public ?string $blockReason,
        public ?int $statusCode,
        public ?string $statusLabel,
        public ?string $reasonCode,
        public ?string $reasonText,
        public bool $requiresAction,
        public ?string $electronicAddress,
    ) {
    }

    public static function of(OutboundTransmission $transmission): self
    {
        $status   = $transmission->currentStatus();
        $address  = $transmission->addressUsed();
        $internal = $transmission->internalBecause();

        return new self(
            clinicId: $transmission->clinicId(),
            documentId: $transmission->trackingId()->toString(),
            sequence: $transmission->sequence(),
            state: $transmission->viewState(),
            blockReason: $transmission->blockReason()?->value,
            statusCode: $status?->code->value,
            statusLabel: $status?->code->label(),
            reasonCode: $status->reasonCode ?? (null !== $internal ? (string) $internal->value : null),
            reasonText: $status->reasonText ?? $transmission->detail(),
            requiresAction: $transmission->requiresAction(),
            electronicAddress: null !== $address ? $address->scheme() . ':' . $address->identifier() : null,
        );
    }
}
