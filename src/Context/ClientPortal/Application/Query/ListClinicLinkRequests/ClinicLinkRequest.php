<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListClinicLinkRequests;

/**
 * A « Rattachement » request in VetApp « Demandes Portal »: an owner
 * account waiting for the clinic to confirm its pending links — or, once
 * answered, the clinic's decision on them (who, when, which files). The
 * Portal profile names are owner text: rendered as text, never as HTML.
 */
final readonly class ClinicLinkRequest
{
    /**
     * @param ?string               $ownerFirstName null when the account has no Portal profile
     * @param \DateTimeImmutable    $requestedAt    the oldest pending link, in the clinic's time zone
     * @param list<LinkRequestFile> $files          the pending files (the decided ones once answered), oldest request first
     * @param ?string               $decisionId     the answer's record, null while it waits
     * @param ?string               $outcome        confirmed|rejected, null while it waits
     * @param ?\DateTimeImmutable   $decidedAt      in the clinic's time zone
     * @param ?string               $decidedByName  the staff member who answered
     */
    public function __construct(
        public string $userId,
        public ?string $ownerFirstName,
        public ?string $ownerLastName,
        public \DateTimeImmutable $requestedAt,
        public array $files,
        public ?string $decisionId = null,
        public ?string $outcome = null,
        public ?\DateTimeImmutable $decidedAt = null,
        public ?string $decidedByName = null,
    ) {
    }

    public function isDecided(): bool
    {
        return null !== $this->decisionId;
    }

    /** « Prénom Nom » of the Portal profile, null without profile. */
    public function ownerName(): ?string
    {
        return null === $this->ownerFirstName && null === $this->ownerLastName ? null : trim($this->ownerFirstName . ' ' . $this->ownerLastName);
    }

    /** One file at least whose last name differs from the profile's. */
    public function hasNameMismatch(): bool
    {
        return [] !== array_filter($this->files, static fn (LinkRequestFile $file): bool => $file->nameMismatch);
    }

    /** One file at least already confirmed-linked to another Portal account. */
    public function hasFileLinkedElsewhere(): bool
    {
        return [] !== array_filter($this->files, static fn (LinkRequestFile $file): bool => $file->linkedToAnotherAccount);
    }
}
