<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Context\ClientPortal\Application\Command\BookPortalAppointment\BookPortalAppointment;
use App\Presentation\Api\Portal\Resource\AppointmentBooking;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\AppointmentResponder;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\View\AppointmentView;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Idempotent by its key (the IdempotentProcessor decorator); a retry under a new key after a lost answer is recognised by Scheduling, which answers the booking already made.
 *
 * @implements ProcessorInterface<AppointmentBooking, AppointmentView>
 */
final readonly class BookAppointmentProcessor implements ProcessorInterface
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
        $clinicId = $uriVariables['clinicId'] ?? null;
        \assert(\is_string($clinicId));

        $this->guard->require($user->id(), $clinicId);

        return $this->appointments->run(
            new BookPortalAppointment($user->id(), $clinicId, $data->petId, $data->motifId, $data->practitionerId, AppointmentResponder::instant($data->startAt)),
            $user->id(),
            $clinicId,
        );
    }
}
