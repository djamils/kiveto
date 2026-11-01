<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** An animal's file as it stands, whoever owns it and whatever its status. */
final readonly class PetState
{
    public const string ALIVE = 'alive';

    public const string DECEASED = 'deceased';

    /**
     * @param ?string $primaryOwnerClientId the active primary owner, if any
     * @param string  $reproductiveStatus   intact|neutered|unknown
     * @param string  $lifeStatus           alive|deceased|missing
     * @param bool    $archived             the file is archived
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $primaryOwnerClientId,
        public string $reproductiveStatus,
        public ?string $microchipNumber,
        public string $lifeStatus,
        public bool $archived,
    ) {
    }

    public function isAlive(): bool
    {
        return self::ALIVE === $this->lifeStatus;
    }

    /** Requests on the pet close with `pet_archived` / `pet_deceased`; null while it is neither. */
    public function closureState(): ?string
    {
        return match (true) {
            $this->archived                      => 'pet_archived',
            self::DECEASED === $this->lifeStatus => 'pet_deceased',
            default                              => null,
        };
    }
}
