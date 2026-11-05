<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\ListClientsByIds;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The files of the clinic among the given ids, by id; an unknown id, or one
 * of another clinic, is left out (where GetClientById answers null).
 */
#[AsMessageHandler]
final readonly class ListClientsByIdsHandler
{
    public function __construct(
        private ClientReadRepositoryInterface $readRepository,
    ) {
    }

    /**
     * @return array<string, ClientView> by client id
     */
    public function __invoke(ListClientsByIds $query): array
    {
        $clientIds = array_values(array_unique($query->clientIds));

        if ([] === $clientIds) {
            return [];
        }

        $clients = [];

        foreach ($this->readRepository->findByIds(ClinicId::fromString($query->clinicId), $clientIds) as $client) {
            $clients[$client->id] = $client;
        }

        return $clients;
    }
}
