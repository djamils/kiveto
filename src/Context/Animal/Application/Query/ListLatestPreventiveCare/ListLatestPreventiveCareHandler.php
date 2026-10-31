<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListLatestPreventiveCare;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListLatestPreventiveCareHandler
{
    public function __construct(
        private AnimalReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return list<LatestPreventiveCareView>
     */
    public function __invoke(ListLatestPreventiveCare $query): array
    {
        if ([] === $query->animalIds) {
            return [];
        }

        return $this->readRepository->listLatestPreventiveCare(
            ClinicId::fromString($query->clinicId),
            $query->animalIds,
        );
    }
}
