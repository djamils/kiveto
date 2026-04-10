<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\ResolveActiveClinic;

use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\AccessibleClinic;
use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\ListClinicsForUser;
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
            default      => $this->resolveMultipleClinics($this->filterAccessibleClinics($accessibleClinics)),
        };
    }

    private function handleSingleClinic(mixed $clinic): ActiveClinicResult
    {
        \assert($clinic instanceof AccessibleClinic);

        return ActiveClinicResult::single($clinic);
    }

    /**
     * @param AccessibleClinic[] $clinics
     */
    private function resolveMultipleClinics(array $clinics): ActiveClinicResult
    {
        $defaults = array_filter($clinics, static fn (AccessibleClinic $clinic): bool => $clinic->isDefault);

        if (1 === \count($defaults)) {
            return ActiveClinicResult::single(reset($defaults));
        }

        return ActiveClinicResult::multiple($clinics);
    }

    /**
     * @param array<mixed> $clinics
     *
     * @return AccessibleClinic[]
     */
    private function filterAccessibleClinics(array $clinics): array
    {
        return array_filter($clinics, static fn (mixed $item): bool => $item instanceof AccessibleClinic);
    }
}
