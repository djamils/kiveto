<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Support;

use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Domain\Exception\TransmissionNotFoundException;
use App\System\ElectronicInvoicing\Domain\Transmission\FlowId;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmission;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\TrackingId;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionKind;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionState;

final class InMemoryTransmissions implements OutboundTransmissionRepositoryInterface
{
    /** @var array<string, OutboundTransmission> */
    public array $items = [];

    public int $saves = 0;

    public function save(OutboundTransmission $transmission): void
    {
        $this->items[$transmission->id()->toString()] = $transmission;
        ++$this->saves;
    }

    public function get(OutboundTransmissionId $id): OutboundTransmission
    {
        return $this->items[$id->toString()] ?? throw new TransmissionNotFoundException($id->toString());
    }

    public function byTrackingId(TrackingId $trackingId): ?OutboundTransmission
    {
        return $this->first(static fn (OutboundTransmission $t): bool => $t->trackingId()->equals($trackingId));
    }

    public function byFlowId(FlowId $flowId): ?OutboundTransmission
    {
        return $this->first(static fn (OutboundTransmission $t): bool => true === $t->flowId()?->equals($flowId));
    }

    public function byDocument(Siren $sellerSiren, TransmissionKind $kind, string $documentNumber): ?OutboundTransmission
    {
        return $this->first(static fn (OutboundTransmission $t): bool => $t->sellerSiren()->equals($sellerSiren) && $kind === $t->kind() && $documentNumber === $t->documentNumber());
    }

    public function awaitingCreditNotesOf(Siren $sellerSiren, string $invoiceNumber): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (OutboundTransmission $t): bool => $t->sellerSiren()->equals($sellerSiren)
                && TransmissionKind::CREDIT_NOTE === $t->kind()
                && $invoiceNumber === $t->precedingDocumentNumber()
                && TransmissionState::AWAITING_INVOICE === $t->state(),
        ));
    }

    /**
     * @param callable(OutboundTransmission): bool $match
     */
    private function first(callable $match): ?OutboundTransmission
    {
        foreach ($this->items as $item) {
            if ($match($item)) {
                return $item;
            }
        }

        return null;
    }
}
