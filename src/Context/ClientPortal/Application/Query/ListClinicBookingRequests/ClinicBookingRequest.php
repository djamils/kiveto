<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListClinicBookingRequests;

/**
 * A « Nouveau client » request in VetApp « Demandes Portal »: who asks (the
 * Portal profile names and the account phone at request time), for which
 * new pet and slot, and the clinic's files whose mobile is that phone.
 * Names are owner text: rendered as text, never as HTML.
 */
final readonly class ClinicBookingRequest
{
    /**
     * @param ?string                  $petSpecies       dog|cat|nac|other
     * @param \DateTimeImmutable       $startAt          in the clinic's time zone
     * @param \DateTimeImmutable       $createdAt        in the clinic's time zone
     * @param string                   $status           pending|confirmed|declined
     * @param ?string                  $declineReason    declined|expired|cancelled|account_deleted
     * @param ?string                  $attachedClientId the file a sibling request was confirmed for (AC 268)
     * @param list<BookingClientMatch> $matches          non-archived files of the clinic with that mobile, oldest first
     */
    public function __construct(
        public string $id,
        public string $userId,
        public ?string $requesterFirstName,
        public ?string $requesterLastName,
        public ?string $requesterPhone,
        public ?string $petName,
        public ?string $petSpecies,
        public string $motif,
        public \DateTimeImmutable $startAt,
        public \DateTimeImmutable $createdAt,
        public string $status,
        public ?string $declineReason,
        public ?string $attachedClientId,
        public string $appointmentId,
        public array $matches,
    ) {
    }

    /** « Prénom Nom » of the requester, null once erased. */
    public function requesterName(): ?string
    {
        return null === $this->requesterFirstName && null === $this->requesterLastName ? null : trim($this->requesterFirstName . ' ' . $this->requesterLastName);
    }

    /** One matching file at least whose last name differs from the profile's. */
    public function hasNameMismatch(): bool
    {
        return [] !== array_filter($this->matches, static fn (BookingClientMatch $match): bool => $match->nameMismatch);
    }

    /** One matching file at least already confirmed-linked to another Portal account. */
    public function hasFileLinkedElsewhere(): bool
    {
        return [] !== array_filter($this->matches, static fn (BookingClientMatch $match): bool => $match->linkedToAnotherAccount);
    }
}
