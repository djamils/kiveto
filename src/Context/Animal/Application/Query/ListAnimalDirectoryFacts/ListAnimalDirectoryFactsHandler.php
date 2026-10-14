<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalDirectoryFacts;

use App\Context\Animal\Application\Port\AnimalDirectoryReadRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListAnimalDirectoryFactsHandler
{
    public function __construct(
        private AnimalDirectoryReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return array<string, AnimalDirectoryFacts>
     */
    public function __invoke(ListAnimalDirectoryFacts $query): array
    {
        if ([] === $query->animalIds) {
            return [];
        }

        return $this->readRepository->facts(
            ClinicId::fromString($query->clinicId),
            $query->animalIds,
            ListAnimalDirectoryFacts::dueBy($query->asOf),
        );
    }
}
