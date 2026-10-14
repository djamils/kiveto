<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Port;

use App\Context\Animal\Application\Query\ListAnimalDirectoryFacts\AnimalDirectoryFacts;
use App\Context\Animal\Application\Query\ListDirectoryMatches\DirectoryMatches;
use App\Context\Animal\Domain\ValueObject\ClinicId;

/**
 * What the clinic's directory (Répertoire) needs to know about animals beyond
 * their own card: which ones match its facets, and the reminders and alerts
 * shown on their row.
 */
interface AnimalDirectoryReadRepositoryInterface
{
    /**
     * Every animal matching all the given facets, with their primary owners.
     *
     * The flags are alternatives: an animal carrying a medical alert OR a due
     * reminder matches when both are asked for. A preventive act is due when
     * the latest act of its protocol is next due on or before $dueBy.
     *
     * @param list<string> $species species to keep, empty means all
     */
    public function matches(
        ClinicId $clinicId,
        ?string $searchTerm,
        array $species,
        bool $withMedicalAlert,
        bool $withDueReminder,
        \DateTimeImmutable $dueBy,
    ): DirectoryMatches;

    /**
     * Medical alerts and due reminders of the given animals, keyed by animal.
     * Animals with neither are left out.
     *
     * @param list<string> $animalIds
     *
     * @return array<string, AnimalDirectoryFacts>
     */
    public function facts(ClinicId $clinicId, array $animalIds, \DateTimeImmutable $dueBy): array;
}
