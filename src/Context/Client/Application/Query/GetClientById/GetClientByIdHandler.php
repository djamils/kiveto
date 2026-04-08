<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientById;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetClientByIdHandler
{
    public function __construct(
        private ClientReadRepositoryInterface $clientReadRepository,
    ) {
    }

    public function __invoke(GetClientById $query): ?ClientView
    {
        $clinicId = ClinicId::fromString($query->clinicId);
        $clientId = ClientId::fromString($query->clientId);

        return $this->clientReadRepository->findById($clinicId, $clientId);
    }
}
