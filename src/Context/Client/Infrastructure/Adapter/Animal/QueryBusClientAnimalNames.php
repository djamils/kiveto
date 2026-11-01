<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Adapter\Animal;

use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\AnimalSummary;
use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\ListAnimalSummariesPerClientIds;
use App\Context\Client\Application\Port\ClientAnimalNamesInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/** Animal's ListAnimalSummariesPerClientIds: active animals, primary owner, by name. */
final readonly class QueryBusClientAnimalNames implements ClientAnimalNamesInterface
{
    /** Enough animals for anyone to recognise a family file. */
    private const int PER_CLIENT = 20;

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function namesPerClient(string $clinicId, array $clientIds): array
    {
        $summaries = $this->queryBus->ask(new ListAnimalSummariesPerClientIds($clinicId, $clientIds, self::PER_CLIENT));
        \assert(\is_array($summaries));

        $names = [];

        foreach ($summaries as $clientId => $animals) {
            \assert(\is_array($animals));
            $names[(string) $clientId] = array_values(array_map(static function (mixed $animal): string {
                \assert($animal instanceof AnimalSummary);

                return $animal->name;
            }, $animals));
        }

        return $names;
    }
}
