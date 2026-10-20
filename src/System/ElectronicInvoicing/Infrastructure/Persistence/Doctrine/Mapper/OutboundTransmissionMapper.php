<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Mapper;

use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionId;
use App\System\ElectronicInvoicing\Domain\Transmission\BlockReason;
use App\System\ElectronicInvoicing\Domain\Transmission\DocumentReference;
use App\System\ElectronicInvoicing\Domain\Transmission\ElectronicAddress;
use App\System\ElectronicInvoicing\Domain\Transmission\FlowId;
use App\System\ElectronicInvoicing\Domain\Transmission\LifecycleEvent;
use App\System\ElectronicInvoicing\Domain\Transmission\LifecycleStatusCode;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmission;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\System\ElectronicInvoicing\Domain\Transmission\TrackingId;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionKind;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionState;
use App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Entity\LifecycleEventEntity;
use App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Entity\OutboundTransmissionEntity;
use Symfony\Component\Uid\Uuid;

final readonly class OutboundTransmissionMapper
{
    /**
     * @param list<LifecycleEventEntity> $events
     */
    public function toDomain(OutboundTransmissionEntity $entity, array $events): OutboundTransmission
    {
        $buyer      = $entity->getBuyerSiren();
        $connection = $entity->getConnectionId();
        $reason     = $entity->getBlockReason();
        $documentId = $entity->getDocumentId();
        $sha256     = $entity->getDocumentSha256();
        $scheme     = $entity->getElectronicAddressScheme();
        $identifier = $entity->getElectronicAddressIdentifier();
        $flowId     = $entity->getFlowId();

        return OutboundTransmission::reconstitute(
            id: OutboundTransmissionId::fromString($entity->getId()->toRfc4122()),
            clinicId: $entity->getClinicId()->toRfc4122(),
            kind: TransmissionKind::from($entity->getKind()),
            trackingId: TrackingId::fromString($entity->getTrackingId()),
            documentNumber: $entity->getDocumentNumber(),
            documentDate: $entity->getDocumentDate(),
            sellerSiren: Siren::fromInput($entity->getSellerSiren()),
            buyerSiren: null !== $buyer ? Siren::fromInput($buyer) : null,
            precedingDocumentNumber: $entity->getPrecedingDocumentNumber(),
            payload: $entity->getPayload(),
            connectionId: null !== $connection ? PlatformConnectionId::fromString($connection->toRfc4122()) : null,
            state: TransmissionState::from($entity->getState()),
            blockReason: null !== $reason ? BlockReason::from($reason) : null,
            detail: $entity->getFailureDetail(),
            document: null !== $documentId && null !== $sha256 ? DocumentReference::of($documentId, $sha256) : null,
            addressUsed: null !== $scheme && null !== $identifier ? ElectronicAddress::of($scheme, $identifier) : null,
            flowId: null !== $flowId ? FlowId::fromString($flowId) : null,
            submissionRequestedAt: $entity->getSubmissionRequestedAt(),
            submissionAttempts: $entity->getSubmissionAttempts(),
            submittedAt: $entity->getSubmittedAt(),
            lifecycle: array_map(
                static fn (LifecycleEventEntity $event): LifecycleEvent => new LifecycleEvent(
                    LifecycleStatusCode::from($event->getStatusCode()),
                    $event->getReasonCode(),
                    $event->getReasonText(),
                    $event->getSourceFlowId(),
                    $event->getOccurredAt(),
                ),
                $events,
            ),
            version: $entity->getVersion(),
            preparedAt: $entity->getPreparedAt(),
        );
    }

    public function toEntity(OutboundTransmission $transmission, ?OutboundTransmissionEntity $entity = null): OutboundTransmissionEntity
    {
        $entity ??= new OutboundTransmissionEntity();

        $entity->setId(Uuid::fromString($transmission->id()->toString()));
        $entity->setClinicId(Uuid::fromString($transmission->clinicId()));
        $entity->setKind($transmission->kind()->value);
        $entity->setTrackingId($transmission->trackingId()->toString());
        $entity->setDocumentNumber($transmission->documentNumber());
        $entity->setDocumentDate($transmission->documentDate());
        $entity->setSellerSiren($transmission->sellerSiren()->value());
        $entity->setBuyerSiren($transmission->buyerSiren()?->value());
        $entity->setPrecedingDocumentNumber($transmission->precedingDocumentNumber());
        $entity->setPayload($transmission->payload());
        $connection = $transmission->connectionId();
        $entity->setConnectionId(null !== $connection ? Uuid::fromString($connection->toString()) : null);
        $entity->setState($transmission->state()->value);
        $entity->setBlockReason($transmission->blockReason()?->value);
        $entity->setFailureDetail($transmission->detail());
        $entity->setDocumentId($transmission->document()?->storageId());
        $entity->setDocumentSha256($transmission->document()?->sha256());
        $entity->setElectronicAddressScheme($transmission->addressUsed()?->scheme());
        $entity->setElectronicAddressIdentifier($transmission->addressUsed()?->identifier());
        $entity->setFlowId($transmission->flowId()?->toString());
        $entity->setSubmissionRequestedAt($transmission->submissionRequestedAt());
        $entity->setSubmissionAttempts($transmission->submissionAttempts());
        $entity->setSubmittedAt($transmission->submittedAt());
        $entity->setVersion($transmission->version());
        $entity->setPreparedAt($transmission->preparedAt());

        return $entity;
    }

    public function eventToEntity(LifecycleEvent $event, OutboundTransmissionId $transmissionId, string $id): LifecycleEventEntity
    {
        $entity = new LifecycleEventEntity();
        $entity->setId(Uuid::fromString($id));
        $entity->setTransmissionId(Uuid::fromString($transmissionId->toString()));
        $entity->setStatusCode($event->code->value);
        $entity->setReasonCode($event->reasonCode);
        $entity->setReasonText(null !== $event->reasonText ? mb_substr($event->reasonText, 0, 512) : null);
        $entity->setSourceFlowId($event->sourceFlowId);
        $entity->setOccurredAt($event->at);

        return $entity;
    }
}
