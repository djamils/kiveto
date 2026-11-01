<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\SubmitRenewalRequest;

use App\Context\ClientPortal\Application\Port\ClientDirectoryInterface;
use App\Context\ClientPortal\Application\Port\PetDirectoryInterface;
use App\Context\ClientPortal\Application\Port\PortalRequestBellInterface;
use App\Context\ClientPortal\Application\Port\PrescriberDirectoryInterface;
use App\Context\ClientPortal\Application\Port\PrescriptionDocumentsInterface;
use App\Context\ClientPortal\Application\Port\PrescriptionPublication;
use App\Context\ClientPortal\Application\Port\PublishedDocumentReadModelInterface;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;
use App\Context\ClientPortal\Domain\Exception\PrescriptionNotFoundException;
use App\Context\ClientPortal\Domain\Exception\PrescriptionNotRenewableException;
use App\Context\ClientPortal\Domain\Request\PetCondition;
use App\Context\ClientPortal\Domain\Request\PortalRequestId;
use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequest;
use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequestRepositoryInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * S6c: a listed prescription of the account (else 404, or 422 once
 * cancelled and withdrawn); an open request is
 * answered as is; else renewable by its owner (issued, no controlled
 * substance, expired allowed), its pet alive, no refusal in the last 7
 * days. Two concurrent submits leave one request: the unique open key
 * makes the second insert a no-op, which then reads the first one.
 */
#[AsMessageHandler]
final readonly class SubmitRenewalRequestHandler
{
    private const array CONDITION_LABELS = [
        'improved'        => 'Amélioration',
        'no_improvement'  => 'Pas d\'amélioration',
        'adverse_effects' => 'Effets indésirables signalés',
    ];

    public function __construct(
        private PublishedDocumentReadModelInterface $documents,
        private PrescriptionDocumentsInterface $prescriptions,
        private PrescriptionRenewalRequestRepositoryInterface $requests,
        private PetDirectoryInterface $pets,
        private ClientDirectoryInterface $clients,
        private PrescriberDirectoryInterface $staff,
        private PortalRequestBellInterface $bell,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SubmitRenewalRequest $command): string
    {
        $type = DocumentType::PRESCRIPTION->value;

        if (null === $this->documents->findBySource($command->userId, $command->clinicId, $type, $command->prescriptionId)) {
            // Withdrawn (cancelled) but the account's: not renewable rather than unknown.
            throw $this->documents->wasPublished($command->userId, $command->clinicId, $type, $command->prescriptionId)
                ? new PrescriptionNotRenewableException($command->prescriptionId, PrescriptionNotRenewableException::NOT_RENEWABLE)
                : new PrescriptionNotFoundException($command->prescriptionId);
        }

        $open = $this->requests->findSubmittedForPrescription($command->clinicId, $command->prescriptionId);

        if (null !== $open) {
            return $open->id()->toString();
        }

        $condition   = PetCondition::tryFrom($command->petCondition) ?? throw new InvalidPortalRequestException('petCondition', \sprintf('État inconnu : « %s ».', $command->petCondition));
        $publication = $this->renewable($command->clinicId, $command->prescriptionId);
        $petId       = (string) $publication->animalId;
        $pet         = $this->pets->statesOf($command->clinicId, [$petId])[$petId] ?? null;
        $now         = $this->clock->now();

        if (null === $pet || !$pet->isAlive() || $pet->archived) {
            throw new PrescriptionNotRenewableException($command->prescriptionId, PrescriptionNotRenewableException::PET_NOT_ALIVE);
        }

        if (true === $this->requests->findLatestRejected($command->clinicId, $command->prescriptionId)?->blocksRenewalAt($now)) {
            throw new PrescriptionNotRenewableException($command->prescriptionId, PrescriptionNotRenewableException::RECENTLY_REJECTED);
        }

        $request = PrescriptionRenewalRequest::submit(
            PortalRequestId::fromString($this->uuids->generate()),
            $command->clinicId,
            $publication->ownerClientId,
            $command->userId,
            $command->prescriptionId,
            $petId,
            $condition,
            $command->note,
            $now,
        );

        if (!$this->requests->add($request)) {
            // A concurrent submit won: its committed request is the answer.
            $winner = $this->requests->findSubmittedForPrescriptionForUpdate($command->clinicId, $command->prescriptionId)
                ?? throw new \LogicException('The open renewal request vanished.');

            return $winner->id()->toString();
        }

        $client = $this->clients->contactOf($command->clinicId, $publication->ownerClientId);
        $this->bell->announce(
            $command->clinicId,
            $this->recipients($command->clinicId, $publication),
            $request->id()->toString(),
            'Demande de renouvellement — ' . $pet->name,
            null === $client ? self::CONDITION_LABELS[$condition->value] : $client->displayName . ' · ' . self::CONDITION_LABELS[$condition->value],
        );

        return $request->id()->toString();
    }

    /**
     * @throws PrescriptionNotRenewableException
     */
    private function renewable(string $clinicId, string $prescriptionId): PrescriptionPublication
    {
        $state       = $this->prescriptions->states($clinicId, [$prescriptionId])[$prescriptionId] ?? null;
        $publication = true === $state?->renewable ? $this->prescriptions->publication($clinicId, $prescriptionId) : null;

        if (null === $publication || null === $publication->animalId) {
            throw new PrescriptionNotRenewableException($prescriptionId, PrescriptionNotRenewableException::NOT_RENEWABLE);
        }

        return $publication;
    }

    /**
     * The prescriber when still a veterinarian of the clinic, else every veterinarian.
     *
     * @return list<string>
     */
    private function recipients(string $clinicId, PrescriptionPublication $publication): array
    {
        $veterinarians = $this->staff->veterinarians($clinicId);

        return \in_array($publication->prescriberUserId, $veterinarians, true) ? [(string) $publication->prescriberUserId] : $veterinarians;
    }
}
