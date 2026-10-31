<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** An animal an owner account may see, as the Animal context describes it. */
final readonly class PortalPet
{
    public const string ALIVE = 'alive';

    /**
     * @param string              $species            dog|cat|nac|other
     * @param string              $sex                male|female|unknown
     * @param string              $reproductiveStatus intact|neutered|unknown
     * @param ?\DateTimeImmutable $birthDate          a date, no time of day
     * @param string              $lifeStatus         alive|deceased|missing
     */
    public function __construct(
        public string $id,
        public string $primaryOwnerClientId,
        public string $name,
        public string $species,
        public ?string $breedName,
        public bool $isMixedBreed,
        public string $sex,
        public string $reproductiveStatus,
        public ?\DateTimeImmutable $birthDate,
        public string $lifeStatus,
        public ?string $microchipNumber,
    ) {
    }

    /** Deceased and missing pets get no care items, no booking, no home entry. */
    public function isAlive(): bool
    {
        return self::ALIVE === $this->lifeStatus;
    }
}
