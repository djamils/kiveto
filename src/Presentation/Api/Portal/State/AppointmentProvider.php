<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Query\GetOwnerAppointmentDetail\GetOwnerAppointmentDetail;
use App\Context\ClientPortal\Application\Query\ListOwnerAppointments\PortalAppointment;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\View\AppointmentView;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<AppointmentView>
 */
final readonly class AppointmentProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AppointmentView
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId      = $uriVariables['clinicId'] ?? null;
        $appointmentId = $uriVariables['appointmentId'] ?? null;
        \assert(\is_string($clinicId) && \is_string($appointmentId));

        $this->guard->require($user->id(), $clinicId);

        $appointment = $this->queryBus->ask(new GetOwnerAppointmentDetail($user->id(), $clinicId, $appointmentId));

        if (!$appointment instanceof PortalAppointment) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        }

        return AppointmentView::of($appointment);
    }
}
