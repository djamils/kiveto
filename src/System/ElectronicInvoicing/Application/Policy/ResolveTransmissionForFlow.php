<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Policy;

use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Application\Port\ParsedLifecycleMessage;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmission;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionKind;

/**
 * Finds the transmission a lifecycle message is about: by (seller SIREN,
 * kind, number) — unique because the numbering of an organisation is
 * unique across its establishments.
 */
final readonly class ResolveTransmissionForFlow
{
    public function __construct(private OutboundTransmissionRepositoryInterface $transmissions)
    {
    }

    public function resolve(string $organisationId, ParsedLifecycleMessage $message): ?OutboundTransmission
    {
        $seller = $message->sellerSiren ?? $organisationId;

        if ($seller !== $organisationId) {
            return null;
        }

        return $this->transmissions->byDocument(
            Siren::fromInput($seller),
            TransmissionKind::fromTypeCode($message->typeCode ?? '380'),
            $message->invoiceNumber,
        );
    }
}
