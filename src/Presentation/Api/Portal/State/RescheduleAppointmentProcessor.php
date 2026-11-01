<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Context\ClientPortal\Application\Command\ReschedulePortalAppointment\ReschedulePortalAppointment;
use App\Presentation\Api\Portal\Resource\AppointmentReschedule;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\AppointmentResponder;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\View\AppointmentView;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Not keyed: moving to the same slot and practitioner is a no-op answered 200.
 *
 * @implements ProcessorInterface<AppointmentReschedule, AppointmentView>
 */
final readonly class RescheduleAppointmentProcessor implements ProcessorInterface
{
    public function __construct(
        private AppointmentResponder $appointments,
        private PortalClinicGuard $guard,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AppointmentView
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $clinicId      = $uriVariables['clinicId'] ?? null;
        $appointmentId = $uriVariables['appointmentId'] ?? null;
        \assert(\is_string($clinicId) && \is_string($appointmentId));

        $this->guard->require($user->id(), $clinicId);

        return $this->appointments->run(
            new ReschedulePortalAppointment($user->id(), $clinicId, $appointmentId, $data->practitionerId, AppointmentResponder::instant($data->startAt)),
            $user->id(),
            $clinicId,
        );
    }
}
