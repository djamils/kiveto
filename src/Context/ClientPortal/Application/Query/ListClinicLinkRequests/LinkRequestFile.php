<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListClinicLinkRequests;

/** A client file of a « Rattachement » request, as staff check it. */
final readonly class LinkRequestFile
{
    /**
     * @param ?string      $firstName              null when the file is no longer known
     * @param list<string> $petNames               active animals of the file
     * @param bool         $nameMismatch           its last name differs from the Portal profile's (case and accents ignored)
     * @param bool         $linkedToAnotherAccount another Portal account has a confirmed link to it
     */
    public function __construct(
        public string $clientId,
        public ?string $firstName,
        public ?string $lastName,
        public array $petNames,
        public bool $nameMismatch,
        public bool $linkedToAnotherAccount,
    ) {
    }

    public function name(): ?string
    {
        return null === $this->firstName && null === $this->lastName ? null : trim($this->firstName . ' ' . $this->lastName);
    }
}
