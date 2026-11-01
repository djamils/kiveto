<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;

/**
 * An owner's request to renew a whole prescription (S6c). « Traiter » is a
 * status flag: the vet writes the new prescription in VetApp as usual and
 * may link it (`resultingPrescriptionId`). At most one submitted request
 * per prescription (unique key on the stored `open_key`).
 */
final class PrescriptionRenewalRequest
{
    use ResolvableRequest;

    public const int MAX_NOTE = 500;

    /** How long a refusal keeps a new request on the prescription out. */
    public const string REJECTION_COOLDOWN = 'P7D';

    private string $prescriptionId;
    private string $petId;
    private PetCondition $petCondition;
    private ?string $note;
    private ?string $resultingPrescriptionId;

    private function __construct()
    {
    }

    /**
     * @throws InvalidPortalRequestException when the note is too long
     */
    public static function submit(
        PortalRequestId $id,
        string $clinicId,
        string $clientId,
        string $userId,
        string $prescriptionId,
        string $petId,
        PetCondition $petCondition,
        ?string $note,
        \DateTimeImmutable $now,
    ): self {
        $note = null === $note || '' === trim($note) ? null : trim($note);

        if (null !== $note && mb_strlen($note) > self::MAX_NOTE) {
            throw new InvalidPortalRequestException('note', \sprintf('La note ne doit pas dépasser %d caractères.', self::MAX_NOTE));
        }

        $request = new self();
        $request->initialise($id, $clinicId, $clientId, $userId, RequestStatus::SUBMITTED, $now, $now, null, null, null, null, false);
        $request->prescriptionId          = $prescriptionId;
        $request->petId                   = $petId;
        $request->petCondition            = $petCondition;
        $request->note                    = $note;
        $request->resultingPrescriptionId = null;

        return $request;
    }

    public static function reconstitute(
        PortalRequestId $id,
        string $clinicId,
        string $clientId,
        string $userId,
        string $prescriptionId,
        string $petId,
        PetCondition $petCondition,
        ?string $note,
        ?string $resultingPrescriptionId,
        RequestStatus $status,
        \DateTimeImmutable $submittedAt,
        \DateTimeImmutable $updatedAt,
        ?RequestResolution $resolution,
        ?\DateTimeImmutable $closedAt,
        ?ClosureReason $closedReason,
        ?string $notificationMessageId,
        bool $clientUnreachable,
    ): self {
        $request = new self();
        $request->initialise($id, $clinicId, $clientId, $userId, $status, $submittedAt, $updatedAt, $resolution, $closedAt, $closedReason, $notificationMessageId, $clientUnreachable);
        $request->prescriptionId          = $prescriptionId;
        $request->petId                   = $petId;
        $request->petCondition            = $petCondition;
        $request->note                    = $note;
        $request->resultingPrescriptionId = $resultingPrescriptionId;

        return $request;
    }

    /**
     * « Traiter » (optionally linking the prescription written for it) or
     * « Refuser » (never linked).
     *
     * @throws \App\Context\ClientPortal\Domain\Exception\PortalRequestAlreadyResolvedException
     * @throws \InvalidArgumentException                                                        when a refusal links a prescription
     */
    public function resolve(RequestResolution $resolution, ?string $resultingPrescriptionId = null): void
    {
        if (null !== $resultingPrescriptionId && RequestStatus::DONE !== $resolution->outcome()) {
            throw new \InvalidArgumentException('Only a processed renewal links the resulting prescription.');
        }

        $this->applyResolution($resolution);
        $this->resultingPrescriptionId = $resultingPrescriptionId;
    }

    /** Whether this request, refused, still keeps a new one out at that instant. */
    public function blocksRenewalAt(\DateTimeImmutable $now): bool
    {
        $resolution = $this->resolution;

        return RequestStatus::REJECTED === $this->status
            && null !== $resolution
            && $resolution->resolvedAt()->add(new \DateInterval(self::REJECTION_COOLDOWN)) > $now;
    }

    /** Account deletion: the owner's note erased. */
    public function eraseFreeText(): void
    {
        $this->note = null;
    }

    public function prescriptionId(): string
    {
        return $this->prescriptionId;
    }

    public function petId(): string
    {
        return $this->petId;
    }

    public function petCondition(): PetCondition
    {
        return $this->petCondition;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function resultingPrescriptionId(): ?string
    {
        return $this->resultingPrescriptionId;
    }
}
