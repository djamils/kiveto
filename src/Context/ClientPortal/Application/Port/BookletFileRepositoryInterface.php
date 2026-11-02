<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The current vaccination booklet file of each animal (one row per animal). */
interface BookletFileRepositoryInterface
{
    /** The storage key of the animal's current booklet, or null when none was rendered. */
    public function currentKey(string $clinicId, string $animalId): ?string;

    /** Whether the key is the current booklet of some animal (rendered again since its replacement). */
    public function isCurrentKey(string $storageKey): bool;

    /** Records the animal's current booklet, replacing the previous one. */
    public function saveCurrent(string $clinicId, string $animalId, string $storageKey, \DateTimeImmutable $at): void;
}
