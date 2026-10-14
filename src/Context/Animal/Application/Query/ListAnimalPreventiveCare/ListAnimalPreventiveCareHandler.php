<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalPreventiveCare;

use App\Context\Animal\Application\Port\PreventiveCareRecordReadRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListAnimalPreventiveCareHandler
{
    public function __construct(
        private PreventiveCareRecordReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return list<PreventiveCareRecordView>
     */
    public function __invoke(ListAnimalPreventiveCare $query): array
    {
        return $this->readRepository->listForAnimal(
            ClinicId::fromString($query->clinicId),
            AnimalId::fromString($query->animalId),
        );
    }
}
