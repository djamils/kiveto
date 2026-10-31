<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Animal;

use App\Context\Animal\Application\Query\ListLatestPreventiveCare\LatestPreventiveCareView;
use App\Context\Animal\Application\Query\ListLatestPreventiveCare\ListLatestPreventiveCare;
use App\Context\ClientPortal\Application\Port\PreventiveAct;
use App\Context\ClientPortal\Application\Port\PreventiveCareInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/** Animal's ListLatestPreventiveCare: the latest act of each protocol. */
final readonly class QueryBusPreventiveCare implements PreventiveCareInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function latestActs(string $clinicId, array $animalIds): array
    {
        if ([] === $animalIds) {
            return [];
        }

        $views = $this->queryBus->ask(new ListLatestPreventiveCare($clinicId, $animalIds));

        if (!\is_array($views)) {
            return [];
        }

        $acts = [];

        foreach ($views as $view) {
            \assert($view instanceof LatestPreventiveCareView);
            $acts[] = new PreventiveAct($view->animalId, $view->kind, $view->label, $view->administeredOn, $view->nextDueOn);
        }

        return $acts;
    }
}
