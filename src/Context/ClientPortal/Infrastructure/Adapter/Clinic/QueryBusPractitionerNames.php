<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Clinic;

use App\Context\ClientPortal\Application\Port\PractitionerNamesInterface;
use App\Context\Clinic\Application\Query\Staff\GetStaffPrintedName\GetStaffPrintedName;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Clinic's GetStaffPrintedName (« Dr Prénom Nom »), once per user: the name
 * online booking lists the practitioners with.
 */
final readonly class QueryBusPractitionerNames implements PractitionerNamesInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function namesOf(string $clinicId, array $userIds): array
    {
        $names = [];

        foreach (array_unique($userIds) as $userId) {
            $name = $this->queryBus->ask(new GetStaffPrintedName($clinicId, $userId));
            $name = \is_string($name) ? mb_trim($name) : '';

            if ('' !== $name) {
                $names[$userId] = $name;
            }
        }

        return $names;
    }
}
