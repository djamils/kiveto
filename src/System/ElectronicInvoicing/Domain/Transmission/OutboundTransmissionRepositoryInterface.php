<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Transmission;

use App\Shared\Domain\ValueObject\Siren;

interface OutboundTransmissionRepositoryInterface
{
    public function save(OutboundTransmission $transmission): void;

    public function get(OutboundTransmissionId $id): OutboundTransmission;

    public function byTrackingId(TrackingId $trackingId): ?OutboundTransmission;

    public function byFlowId(FlowId $flowId): ?OutboundTransmission;

    public function byDocument(Siren $sellerSiren, TransmissionKind $kind, string $documentNumber): ?OutboundTransmission;

    /**
     * The credit notes of the seller's invoice $invoiceNumber still waiting for it.
     *
     * @return list<OutboundTransmission>
     */
    public function awaitingCreditNotesOf(Siren $sellerSiren, string $invoiceNumber): array;
}
