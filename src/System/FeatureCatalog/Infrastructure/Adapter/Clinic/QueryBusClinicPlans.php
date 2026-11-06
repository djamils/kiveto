<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Infrastructure\Adapter\Clinic;

use App\Context\Clinic\Application\Query\Account\GetClinicAccount\ClinicAccountView;
use App\Context\Clinic\Application\Query\Account\GetClinicAccount\GetClinicAccount;
use App\Context\Clinic\Application\Query\Console\ConsoleClinicRow;
use App\Context\Clinic\Application\Query\Console\ListConsoleClinics;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\FeatureCatalog\Application\Port\ClinicPlansInterface;

/**
 * The plans from the Clinic context: one clinic's account, or the console's
 * list of the clinics for several.
 */
final readonly class QueryBusClinicPlans implements ClinicPlansInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    public function plansOf(array $clinicIds): array
    {
        if (1 === \count($clinicIds)) {
            $account = $this->queryBus->ask(new GetClinicAccount($clinicIds[0]));

            return $account instanceof ClinicAccountView ? [$account->clinicId => $account->plan] : [];
        }

        $wanted = array_flip($clinicIds);
        $plans  = [];
        $rows   = $this->queryBus->ask(new ListConsoleClinics());

        foreach (\is_array($rows) ? $rows : [] as $row) {
            if ($row instanceof ConsoleClinicRow && isset($wanted[$row->id])) {
                $plans[$row->id] = $row->plan;
            }
        }

        return $plans;
    }
}
