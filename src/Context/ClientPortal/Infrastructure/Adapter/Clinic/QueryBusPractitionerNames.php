<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Clinic;

use App\Context\ClientPortal\Application\Port\PractitionerNamesInterface;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Shared\Application\Bus\QueryBusInterface;

/** Clinic's ListClinicVeterinarians, once per call, then looked up by user. */
final readonly class QueryBusPractitionerNames implements PractitionerNamesInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function namesOf(string $clinicId, array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }

        $veterinarians = $this->queryBus->ask(new ListClinicVeterinarians($clinicId));

        if (!\is_array($veterinarians)) {
            return [];
        }

        $names = [];

        foreach ($veterinarians as $veterinarian) {
            \assert($veterinarian instanceof ClinicVeterinarianItem);
            $name = null === $veterinarian->displayName ? '' : mb_trim($veterinarian->displayName);

            if ('' !== $name && \in_array($veterinarian->userId, $userIds, true)) {
                $names[$veterinarian->userId] = $name;
            }
        }

        return $names;
    }
}
