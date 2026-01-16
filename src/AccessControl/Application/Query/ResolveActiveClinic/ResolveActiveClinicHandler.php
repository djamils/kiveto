<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Query\ResolveActiveClinic;

use App\AccessControl\Application\Query\ListClinicsForUser\AccessibleClinic;
use App\AccessControl\Application\Query\ListClinicsForUser\ListClinicsForUser;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ResolveActiveClinicHandler
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(ResolveActiveClinic $query): ActiveClinicResult
    {
        $accessibleClinics = $this->queryBus->ask(new ListClinicsForUser($query->userId));
        \assert(\is_array($accessibleClinics));

        $count = \count($accessibleClinics);

        return match (true) {
            0 === $count => ActiveClinicResult::none(),
            1 === $count => $this->handleSingleClinic($accessibleClinics[0]),
            default      => ActiveClinicResult::multiple($accessibleClinics),
        };
    }

    private function handleSingleClinic(mixed $clinic): ActiveClinicResult
    {
        \assert($clinic instanceof AccessibleClinic);

        return ActiveClinicResult::single($clinic);
    }
}
