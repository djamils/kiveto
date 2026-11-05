<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Animal;

use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\AnimalSummary;
use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\ListAnimalSummariesPerClientIds;
use App\Context\ClientPortal\Application\Port\PetNamesInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Animal's ListAnimalSummariesPerClientIds: the client's active animals
 * (archived and deceased ones excluded by the query), as primary owner.
 */
final readonly class QueryBusPetNames implements PetNamesInterface
{
    /** Far more than a household has; the query caps each client's list. */
    private const int LIMIT = 50;

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function petNamesOf(string $clinicId, string $clientId): array
    {
        return $this->petNamesOfClients($clinicId, [$clientId])[$clientId] ?? [];
    }

    public function petNamesOfClients(string $clinicId, array $clientIds): array
    {
        if ([] === $clientIds) {
            return [];
        }

        $summaries = $this->queryBus->ask(new ListAnimalSummariesPerClientIds($clinicId, $clientIds, self::LIMIT));
        $names     = [];

        foreach (\is_array($summaries) ? $summaries : [] as $clientId => $list) {
            foreach (\is_array($list) ? $list : [] as $summary) {
                \assert($summary instanceof AnimalSummary);
                $names[(string) $clientId][] = $summary->name;
            }
        }

        return $names;
    }
}
