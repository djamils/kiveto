<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Query\GetTransmissionStatus;

use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\TrackingId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetTransmissionStatusHandler
{
    public function __construct(private OutboundTransmissionRepositoryInterface $transmissions)
    {
    }

    public function __invoke(GetTransmissionStatus $query): ?TransmissionStatusView
    {
        $transmission = $this->transmissions->byTrackingId(TrackingId::fromString($query->documentId));

        return null === $transmission ? null : TransmissionStatusView::of($transmission);
    }
}
