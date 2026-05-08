<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientNamesByIds;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetClientNamesByIdsHandler
{
    public function __construct(
        private ClientReadRepositoryInterface $clientReadRepository,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function __invoke(GetClientNamesByIds $query): array
    {
        if ([] === $query->clientIds) {
            return [];
        }

        return $this->clientReadRepository->findFullNamesByIds(
            ClinicId::fromString($query->clinicId),
            $query->clientIds,
        );
    }
}
