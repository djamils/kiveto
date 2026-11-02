<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use App\Context\ClientPortal\Application\Command\DeclineBookingRequest\DeclineBookingRequest;
use App\Context\ClientPortal\Domain\Exception\BookingRequestAlreadyResolvedException;
use App\Context\ClientPortal\Domain\Exception\BookingRequestNotFoundException;
use App\Context\Scheduling\Domain\Exception\AppointmentAlreadyHandledException;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Presentation\Clinic\ViewModel\Client\StaffNameResolver;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * « Refuser » a « Nouveau client » request: the slot is freed and the
 * owner told by SMS that the request was not confirmed. Needs
 * `manage_animals`.
 */
#[Route('/portal-requests/nouveaux-clients/{id}/decline', name: 'clinic_portal_booking_requests_decline', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
final class DeclineBookingRequestController extends AbstractPortalRequestAnswer
{
    protected const string CSRF_ID = 'portal_booking_request';

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly ClinicPermissionGuard $permissions,
        private readonly StaffNameResolver $staffNames,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);
        $this->permissions->require(ClinicPermissionGuard::MANAGE_ANIMALS);

        $clinicId              = $this->clinicId();
        [$actorId, $actorName] = $this->actor($this->staffNames, $clinicId);

        try {
            $this->commandBus->dispatch(new DeclineBookingRequest($clinicId, $id, $actorId, $actorName));
        } catch (BookingRequestNotFoundException) {
            throw $this->createNotFoundException('Demande introuvable.');
        } catch (BookingRequestAlreadyResolvedException) {
            return $this->alreadyHandled($request, $id);
        } catch (AppointmentAlreadyHandledException) {
            return $this->alreadyHandled($request, $id);
        }

        return $this->reply($request, true, 'Demande refusée — le créneau est libéré et le propriétaire prévenu par SMS.', 'clinic_portal_requests');
    }

    private function alreadyHandled(Request $request, string $id): Response
    {
        return $this->reply($request, false, 'Demande déjà traitée.', 'clinic_portal_booking_requests_show', ['id' => $id]);
    }
}
