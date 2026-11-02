<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Context\ClientPortal\Application\Command\BookPortalAppointmentWithNewPet\BookPortalAppointmentWithNewPet;
use App\Presentation\Api\Portal\Resource\AppointmentBookingWithNewPet;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\AppointmentResponder;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Portal\View\AppointmentView;
use App\Presentation\Api\Shared\Idempotency\IdempotentProcessor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

/**
 * Idempotent by its key; a retry of the same request (same key) whose answer was lost reuses the animal it registered
 * and the booking already made (S4B-01). A new key registers a new animal.
 *
 * @implements ProcessorInterface<AppointmentBookingWithNewPet, AppointmentView>
 */
final readonly class BookAppointmentWithNewPetProcessor implements ProcessorInterface
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

        $request = $context['request'] ?? null;
        $key     = $request instanceof Request ? $request->headers->get(IdempotentProcessor::HEADER) : null;

        return $this->appointments->run(
            new BookPortalAppointmentWithNewPet($user->id(), $clinicId, $data->newPet->name, $data->newPet->species, $data->motifId, $data->practitionerId, AppointmentResponder::instant($data->startAt), $key),
            $user->id(),
            $clinicId,
        );
    }
}
