<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

/**
 * An owner's request to change a field of a pet's file (name, weight,
 * sterilization, chip number, or anything else in a note). The clinic
 * applies it by hand: the animal never changes before. The pet's value at
 * submit is snapshotted so the clinic sees what the owner saw.
 */
final class PetChangeRequest
{
    use ResolvableRequest;

    private string $petId;
    private ProposedChange $change;
    private ?string $currentValueAtSubmit;

    private function __construct()
    {
    }

    /**
     * @param ?string $currentValueAtSubmit the pet's value for the field when asked (weight in grams), null when unknown
     */
    public static function submit(
        PortalRequestId $id,
        string $clinicId,
        string $clientId,
        string $userId,
        string $petId,
        ProposedChange $change,
        ?string $currentValueAtSubmit,
        \DateTimeImmutable $now,
    ): self {
        $request = new self();
        $request->initialise($id, $clinicId, $clientId, $userId, RequestStatus::SUBMITTED, $now, $now, null, null, null, null, false);
        $request->petId                = $petId;
        $request->change               = $change;
        $request->currentValueAtSubmit = $currentValueAtSubmit;

        return $request;
    }

    public static function reconstitute(
        PortalRequestId $id,
        string $clinicId,
        string $clientId,
        string $userId,
        string $petId,
        ProposedChange $change,
        ?string $currentValueAtSubmit,
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
        $request->petId                = $petId;
        $request->change               = $change;
        $request->currentValueAtSubmit = $currentValueAtSubmit;

        return $request;
    }

    /**
     * « Traiter » or « Refuser ».
     *
     * @throws \App\Context\ClientPortal\Domain\Exception\PortalRequestAlreadyResolvedException
     */
    public function resolve(RequestResolution $resolution): void
    {
        $this->applyResolution($resolution);
    }

    /** Account deletion: the owner's note (and the free value of `other`) erased. */
    public function eraseFreeText(): void
    {
        $this->change = $this->change->withoutFreeText();
    }

    public function petId(): string
    {
        return $this->petId;
    }

    public function change(): ProposedChange
    {
        return $this->change;
    }

    public function currentValueAtSubmit(): ?string
    {
        return $this->currentValueAtSubmit;
    }
}
