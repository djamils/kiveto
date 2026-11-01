<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListClinicLinkRequests;

/**
 * A « Rattachement » request in VetApp « Demandes Portal »: an owner
 * account waiting for the clinic to confirm its pending links. The Portal
 * profile names are owner text: rendered as text, never as HTML.
 */
final readonly class ClinicLinkRequest
{
    /**
     * @param ?string               $ownerFirstName null when the account has no Portal profile
     * @param \DateTimeImmutable    $requestedAt    the oldest pending link, in the clinic's time zone
     * @param list<LinkRequestFile> $files          the pending files, oldest request first
     */
    public function __construct(
        public string $userId,
        public ?string $ownerFirstName,
        public ?string $ownerLastName,
        public \DateTimeImmutable $requestedAt,
        public array $files,
    ) {
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
