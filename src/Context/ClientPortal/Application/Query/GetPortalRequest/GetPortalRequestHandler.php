<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPortalRequest;

use App\Context\ClientPortal\Application\Port\AnimalWeightsInterface;
use App\Context\ClientPortal\Application\Port\ClientDirectoryInterface;
use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\PetDirectoryInterface;
use App\Context\ClientPortal\Application\Port\PetState;
use App\Context\ClientPortal\Domain\Request\ChangeField;
use App\Context\ClientPortal\Domain\Request\PetChangeRequest;
use App\Context\ClientPortal\Domain\Request\PetChangeRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\PortalRequestKind;
use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequest;
use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetPortalRequestHandler
{
    public function __construct(
        private PetChangeRequestRepositoryInterface $changes,
        private PrescriptionRenewalRequestRepositoryInterface $renewals,
        private PetDirectoryInterface $pets,
        private AnimalWeightsInterface $weights,
        private ClientDirectoryInterface $clients,
        private ClinicProfileInterface $clinics,
    ) {
    }

    public function __invoke(GetPortalRequest $query): ?PortalRequestDetails
    {
        $request = $this->changes->findById($query->clinicId, $query->requestId)
            ?? $this->renewals->findById($query->clinicId, $query->requestId);

        if (null === $request) {
            return null;
        }

        $zone       = new \DateTimeZone($this->clinics->clinicOf($query->clinicId)->timeZone);
        $pet        = $this->pets->statesOf($query->clinicId, [$request->petId()])[$request->petId()] ?? null;
        $change     = $request instanceof PetChangeRequest ? $request->change() : null;
        $renewal    = $request instanceof PrescriptionRenewalRequest ? $request : null;
        $resolution = $request->resolution();
        $status     = $request->isSubmitted() && null !== $pet?->closureState() ? RequestStatus::CLOSED->value : $request->status()->value;

        return new PortalRequestDetails(
            id: $request->id()->toString(),
            kind: (null === $renewal ? PortalRequestKind::PET_CHANGE : PortalRequestKind::PRESCRIPTION_RENEWAL)->value,
            clientId: $request->clientId(),
            clientName: $this->clients->contactOf($query->clinicId, $request->clientId())?->displayName,
            userId: $request->userId(),
            petId: $request->petId(),
            petName: $pet?->name,
            field: $change?->field()->value,
            proposedValue: $change?->proposedValue(),
            normalisedValue: $change?->normalised() ?? [],
            currentValueAtSubmit: $request instanceof PetChangeRequest ? $request->currentValueAtSubmit() : null,
            currentValue: null === $change ? null : $this->currentValue($query->clinicId, $request->petId(), $change->field(), $pet),
            prescriptionId: $renewal?->prescriptionId(),
            petCondition: $renewal?->petCondition()->value,
            note: $change?->note() ?? $renewal?->note(),
            status: $status,
            closedReason: $request->closedReason()->value ?? (RequestStatus::CLOSED->value === $status ? $pet?->closureState() : null),
            submittedAt: $request->submittedAt()->setTimezone($zone),
            resolvedAt: $resolution?->resolvedAt()->setTimezone($zone),
            resolvedByName: $resolution?->resolvedByName(),
            resolutionMessage: $resolution?->message(),
            resultingPrescriptionId: $renewal?->resultingPrescriptionId(),
            notificationMessageId: $request->notificationMessageId(),
            clientUnreachable: $request->isClientUnreachable(),
        );
    }

    /** The pet's value of the field now; the weight in grams. */
    private function currentValue(string $clinicId, string $petId, ChangeField $field, ?PetState $pet): ?string
    {
        return match ($field) {
            ChangeField::NAME          => $pet?->name,
            ChangeField::WEIGHT        => null === ($weight = $this->weights->latestOf($clinicId, $petId)) ? null : (string) $weight->weightGrams,
            ChangeField::STERILIZATION => $pet?->reproductiveStatus,
            ChangeField::CHIP_NUMBER   => $pet?->microchipNumber,
            ChangeField::OTHER         => null,
        };
    }
}
