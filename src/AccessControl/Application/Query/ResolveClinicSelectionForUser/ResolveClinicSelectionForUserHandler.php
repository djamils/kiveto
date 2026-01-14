<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Query\ResolveClinicSelectionForUser;

use App\AccessControl\Application\Query\ListClinicsForUser\ListClinicsForUser;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ResolveClinicSelectionForUserHandler
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(ResolveClinicSelectionForUser $query): ClinicSelectionDecision
    {
        $accessibleClinics = $this->queryBus->ask(new ListClinicsForUser($query->userId));
        \assert(\is_array($accessibleClinics));

        $count = \count($accessibleClinics);

        return match (true) {
            0 === $count => ClinicSelectionDecision::none(),
            1 === $count => ClinicSelectionDecision::single($accessibleClinics[0]),
            default      => ClinicSelectionDecision::multiple($accessibleClinics),
        };
    }
}
