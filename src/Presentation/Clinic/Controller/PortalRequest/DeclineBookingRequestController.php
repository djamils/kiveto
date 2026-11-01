<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use App\Context\ClientPortal\Application\Command\DeclineBookingRequest\DeclineBookingRequest;
use App\Context\ClientPortal\Domain\Exception\BookingRequestAlreadyResolvedException;
use App\Context\ClientPortal\Domain\Exception\BookingRequestNotFoundException;
use App\Context\Scheduling\Domain\Exception\AppointmentAlreadyHandledException;
use App\Presentation\Clinic\Controller\Shared\AbstractSheetAction;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
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
final class DeclineBookingRequestController extends AbstractSheetAction
{
    protected const string CSRF_ID = 'portal_booking_request';

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly ClinicPermissionGuard $permissions,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);
        $this->permissions->require(ClinicPermissionGuard::MANAGE_ANIMALS);

        $user = $this->getUser();
        \assert($user instanceof SecurityUser);

        try {
            $this->commandBus->dispatch(new DeclineBookingRequest($this->clinicId(), $id, $user->id()));
        } catch (BookingRequestNotFoundException) {
            throw $this->createNotFoundException('Demande introuvable.');
        } catch (BookingRequestAlreadyResolvedException) {
            return $this->alreadyHandled($id);
        } catch (AppointmentAlreadyHandledException) {
            return $this->alreadyHandled($id);
        }

        $this->addFlash('success', 'Demande refusée — le créneau est libéré et le propriétaire prévenu par SMS.');

        return $this->redirectToRoute('clinic_portal_requests', [], Response::HTTP_SEE_OTHER);
    }

    private function alreadyHandled(string $id): Response
    {
        $this->addFlash('error', 'Demande déjà traitée.');

        return $this->redirectToRoute('clinic_portal_booking_requests_show', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
