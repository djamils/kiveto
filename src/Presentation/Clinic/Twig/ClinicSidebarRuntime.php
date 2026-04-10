<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Twig;

use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\AccessibleClinic;
use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\ListClinicsForUser;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Provides clinic sidebar data (accessible clinics + active clinic ID)
 * for the sidebar template without requiring each controller to pass it.
 */
final readonly class ClinicSidebarRuntime
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CurrentClinicContextInterface $currentClinicContext,
        private Security $security,
    ) {
    }

    /**
     * @return array{clinics: list<AccessibleClinic>, activeClinicId: string|null}
     */
    public function getSidebarData(): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof SecurityUser) {
            return ['clinics' => [], 'activeClinicId' => null];
        }

        $clinics = $this->queryBus->ask(new ListClinicsForUser($user->id()));
        \assert(\is_array($clinics));

        /** @var list<AccessibleClinic> $clinics */
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();

        return [
            'clinics'        => $clinics,
            'activeClinicId' => $currentClinicId?->toString(),
        ];
    }
}
