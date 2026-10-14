<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListDirectoryMatches;

use App\Context\Animal\Application\Port\AnimalDirectoryReadRepositoryInterface;
use App\Context\Animal\Application\Query\ListAnimalDirectoryFacts\ListAnimalDirectoryFacts;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListDirectoryMatchesHandler
{
    public function __construct(
        private AnimalDirectoryReadRepositoryInterface $readRepository,
    ) {
    }

    public function __invoke(ListDirectoryMatches $query): DirectoryMatches
    {
        $term = null === $query->searchTerm ? null : trim($query->searchTerm);

        return $this->readRepository->matches(
            ClinicId::fromString($query->clinicId),
            '' === $term ? null : $term,
            $query->species,
            $query->withMedicalAlert,
            $query->withDueReminder,
            ListAnimalDirectoryFacts::dueBy($query->asOf),
        );
    }
}
