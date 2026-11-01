<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\PortalRequest;

use App\Context\ClientPortal\Application\Command\ConfirmBookingRequest\ConfirmBookingRequest;
use App\Context\ClientPortal\Domain\Exception\BookingRequestAlreadyResolvedException;
use App\Context\ClientPortal\Domain\Exception\BookingRequestNotFoundException;
use App\Context\ClientPortal\Domain\Exception\InvalidBookingChoiceException;
use App\Context\Scheduling\Domain\Exception\AppointmentAlreadyHandledException;
use App\Context\Scheduling\Domain\Exception\PendingAppointmentExpiredException;
use App\Presentation\Clinic\Controller\Shared\AbstractSheetAction;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * « Confirmer » a « Nouveau client » request: the chosen file (or a new
 * one), the chosen animal (or the new pet), the client link and the
 * appointment confirmed in one go; the owner then finds the clinic in the
 * Portal. A colleague, the expiry or the agenda that answered first wins
 * (« Demande déjà traitée. »). Needs `manage_animals`.
 */
#[Route('/portal-requests/nouveaux-clients/{id}/confirm', name: 'clinic_portal_booking_requests_confirm', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
final class ConfirmBookingRequestController extends AbstractSheetAction
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

        // « client »: a matching file id, or « new »; « animal »: an animal id, or « new »
        $client = $request->request->getString('client');
        $animal = $request->request->getString('animal');

        try {
            $this->commandBus->dispatch(new ConfirmBookingRequest(
                $this->clinicId(),
                $id,
                ConfirmBookingRequest::NEW === $client ? ConfirmBookingRequest::NEW : ConfirmBookingRequest::EXISTING,
                ConfirmBookingRequest::NEW === $client || '' === $client ? null : $client,
                ConfirmBookingRequest::NEW === $animal ? ConfirmBookingRequest::NEW : ConfirmBookingRequest::EXISTING,
                ConfirmBookingRequest::NEW === $animal || '' === $animal ? null : $animal,
                $user->id(),
            ));
        } catch (BookingRequestNotFoundException) {
            throw $this->createNotFoundException('Demande introuvable.');
        } catch (InvalidBookingChoiceException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('clinic_portal_booking_requests_show', ['id' => $id], Response::HTTP_SEE_OTHER);
        } catch (BookingRequestAlreadyResolvedException) {
            return $this->alreadyHandled($id);
        } catch (AppointmentAlreadyHandledException) {
            return $this->alreadyHandled($id);
        } catch (PendingAppointmentExpiredException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('clinic_portal_booking_requests_show', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', 'Rendez-vous confirmé — le compte Portal est rattaché au dossier client.');

        return $this->redirectToRoute('clinic_portal_requests', [], Response::HTTP_SEE_OTHER);
    }

    private function alreadyHandled(string $id): Response
    {
        $this->addFlash('error', 'Demande déjà traitée.');

        return $this->redirectToRoute('clinic_portal_booking_requests_show', ['id' => $id], Response::HTTP_SEE_OTHER);
    }
}
