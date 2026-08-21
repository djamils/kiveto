<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientCities;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListClientCitiesHandler
{
    public function __construct(
        private ClientReadRepositoryInterface $clientReadRepository,
    ) {
    }

    /**
     * @return list<string>
     */
    public function __invoke(ListClientCities $query): array
    {
        return $this->clientReadRepository->listCities(ClinicId::fromString($query->clinicId));
    }
}
