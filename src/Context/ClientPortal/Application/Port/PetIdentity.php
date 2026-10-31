<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** Who an animal is, as its vaccination booklet prints it. */
final readonly class PetIdentity
{
    /**
     * @param string              $species   dog|cat|nac|other
     * @param string              $sex       male|female|unknown
     * @param ?\DateTimeImmutable $birthDate a date, no time of day
     */
    public function __construct(
        public string $name,
        public string $species,
        public ?string $breedName,
        public bool $isMixedBreed,
        public string $sex,
        public ?\DateTimeImmutable $birthDate,
        public ?string $microchipNumber,
    ) {
    }
}
