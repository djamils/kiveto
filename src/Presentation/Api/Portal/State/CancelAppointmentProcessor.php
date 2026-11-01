<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Context\ClientPortal\Application\Command\CancelPortalAppointment\CancelPortalAppointment;
use App\Presentation\Api\Portal\Resource\AppointmentCancellation;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\AppointmentResponder;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\View\AppointmentView;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Not keyed: cancelling an appointment the owner already cancelled answers it again (200, same body).
 *
 * @implements ProcessorInterface<AppointmentCancellation, AppointmentView>
 */
final readonly class CancelAppointmentProcessor implements ProcessorInterface
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
            new CancelPortalAppointment($user->id(), $clinicId, $appointmentId, $data->reason),
            $user->id(),
            $clinicId,
        );
    }
}
