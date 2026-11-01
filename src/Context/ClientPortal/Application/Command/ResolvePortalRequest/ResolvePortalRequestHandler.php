<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ResolvePortalRequest;

use App\Context\ClientPortal\Application\Port\ClientDirectoryInterface;
use App\Context\ClientPortal\Application\Port\NotificationRequesterInterface;
use App\Context\ClientPortal\Application\Port\PetDirectoryInterface;
use App\Context\ClientPortal\Application\Port\PortalRequestResolvedMessage;
use App\Context\ClientPortal\Application\Port\PrescriptionDocumentsInterface;
use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;
use App\Context\ClientPortal\Domain\Exception\PortalRequestNotFoundException;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\PetChangeRequest;
use App\Context\ClientPortal\Domain\Request\PetChangeRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\PortalRequestKind;
use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequest;
use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\RequestResolution;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Shared\Domain\Time\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Under `FOR UPDATE` (two staff answering at once: the second gets the
 * domain guard). A request whose pet is archived or deceased is closed
 * instead (read-time rule made durable). The owner message is requested
 * last, under the key `portal-request-resolved:<id>`; a client without
 * reachable address is flagged « client injoignable ».
 */
#[AsMessageHandler]
final readonly class ResolvePortalRequestHandler
{
    public function __construct(
        private PetChangeRequestRepositoryInterface $changes,
        private PrescriptionRenewalRequestRepositoryInterface $renewals,
        private PetDirectoryInterface $pets,
        private PrescriptionDocumentsInterface $prescriptions,
        private ClientDirectoryInterface $clients,
        private NotificationRequesterInterface $notifications,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ResolvePortalRequest $command): string
    {
        $request = $this->changes->findByIdForUpdate($command->clinicId, $command->requestId)
            ?? $this->renewals->findByIdForUpdate($command->clinicId, $command->requestId)
            ?? throw new PortalRequestNotFoundException($command->requestId);
        $outcome = RequestStatus::tryFrom($command->outcome) ?? throw new \InvalidArgumentException(\sprintf('Unknown outcome "%s".', $command->outcome));
        $now     = $this->clock->now();
        $pet     = $this->pets->statesOf($command->clinicId, [$request->petId()])[$request->petId()] ?? null;
        $closure = $pet?->closureState();

        if (null !== $closure && $request->close(ClosureReason::from($closure), $now)) {
            $this->save($request);

            return RequestStatus::CLOSED->value;
        }

        $resolution = RequestResolution::of($outcome, $command->actorUserId, $command->actorName, $command->message, $now);

        if ($request instanceof PrescriptionRenewalRequest) {
            $request->resolve($resolution, $this->resultingPrescription($request, $command->resultingPrescriptionId));
        } elseif (null !== $command->resultingPrescriptionId) {
            throw new \InvalidArgumentException('Only a renewal links a resulting prescription.');
        } else {
            $request->resolve($resolution);
        }

        $this->save($request);
        $this->notify($request, $resolution, $pet->name ?? 'votre animal');
        $this->save($request);

        return $request->status()->value;
    }

    /**
     * @throws InvalidPortalRequestException when not an issued prescription of the pet issued after the request
     */
    private function resultingPrescription(PrescriptionRenewalRequest $request, ?string $prescriptionId): ?string
    {
        if (null === $prescriptionId) {
            return null;
        }

        $publication = $this->prescriptions->publication($request->clinicId(), $prescriptionId);

        if (null === $publication || $publication->animalId !== $request->petId() || $publication->issuedAt <= $request->submittedAt()) {
            throw new InvalidPortalRequestException('resultingPrescriptionId', 'Choisissez une ordonnance émise pour cet animal après la demande.');
        }

        return $publication->prescriptionId;
    }

    private function notify(PetChangeRequest|PrescriptionRenewalRequest $request, RequestResolution $resolution, string $petName): void
    {
        $contact = $this->clients->contactOf($request->clinicId(), $request->clientId());
        $channel = $contact?->transactionalChannel;
        $address = 'sms' === $channel ? $contact?->mobile : $contact?->email;

        if (null === $contact || null === $channel || null === $address) {
            $request->markClientUnreachable();
            $this->logger->warning('The answer to a Portal request could not be sent: the client has no reachable address.', [
                'clinicId'  => $request->clinicId(),
                'requestId' => $request->id()->toString(),
            ]);

            return;
        }

        $kind     = $request instanceof PrescriptionRenewalRequest ? PortalRequestKind::PRESCRIPTION_RENEWAL : PortalRequestKind::PET_CHANGE;
        $sentence = RequestStatus::DONE === $resolution->outcome()
            ? \sprintf('Votre demande de %s pour %s a été traitée.', $kind->label(), $petName)
            : \sprintf('Votre demande de %s pour %s n\'a pas pu être acceptée.', $kind->label(), $petName);

        $request->attachNotification($this->notifications->requestPortalRequestResolved(new PortalRequestResolvedMessage(
            requestId: $request->id()->toString(),
            clinicId: $request->clinicId(),
            clientId: $request->clientId(),
            channel: $channel,
            recipientAddress: $address,
            recipientName: $contact->displayName,
            petName: $petName,
            sentence: $sentence,
            staffMessage: $resolution->message() ?? '',
            fallbackEmail: 'sms' === $channel ? $contact->email : null,
        )));
    }

    private function save(PetChangeRequest|PrescriptionRenewalRequest $request): void
    {
        if ($request instanceof PrescriptionRenewalRequest) {
            $this->renewals->save($request);

            return;
        }

        $this->changes->save($request);
    }
}
