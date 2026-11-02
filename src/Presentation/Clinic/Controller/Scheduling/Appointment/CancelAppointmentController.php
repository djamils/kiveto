<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\Appointment;

use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Context\Scheduling\Application\Query\GetAppointmentClinicId\GetAppointmentClinicId;
use App\Context\Scheduling\Application\Query\GetAppointmentDetails\AppointmentDetails;
use App\Context\Scheduling\Application\Query\GetAppointmentDetails\GetAppointmentDetails;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * « Annuler » on the agenda. A pending online request cancelled here is
 * declined, so it needs `manage_appointments` like « Refuser » (S4A-01).
 */
#[Route(
    '/scheduling/appointments/{appointmentId}/cancel',
    name: 'clinic_scheduling_appointment_cancel',
    methods: ['POST'],
)]
final class CancelAppointmentController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly ClinicPermissionGuard $permissions,
    ) {
    }

    public function __invoke(Request $request, string $appointmentId): JsonResponse
    {
        $token = $request->request->getString('_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('appointment', $token))) {
            return new JsonResponse(
                ['success' => false, 'errors' => ['global' => ['Token CSRF invalide.']], 'errorCode' => 'CSRF_INVALID'],
                Response::HTTP_FORBIDDEN,
            );
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        // Multi-tenant guard
        $clinicId = $this->queryBus->ask(new GetAppointmentClinicId($appointmentId));
        if (null === $clinicId || $clinicId !== $currentClinicId->toString()) {
            throw $this->createNotFoundException();
        }

        $details = $this->queryBus->ask(new GetAppointmentDetails($appointmentId));
        if ($details instanceof AppointmentDetails && $details->isPending && !$this->permissions->has(ClinicPermissionGuard::MANAGE_APPOINTMENTS)) {
            return new JsonResponse(
                ['success' => false, 'errors' => ['global' => ['Vous n’avez pas le droit de traiter les demandes de rendez-vous.']], 'errorCode' => 'FORBIDDEN'],
                Response::HTTP_FORBIDDEN,
            );
        }

        try {
            $this->commandBus->dispatch(new CancelAppointment(
                appointmentId: $appointmentId,
                clinicId: $currentClinicId->toString(),
                // A pending request cancelled here is declined (APPOINTMENT_DECLINED)
                cancelledBy: CancellationActor::CLINIC->value,
            ));

            return new JsonResponse([
                'success'          => true,
                'appointmentId'    => $appointmentId,
                'alreadyCancelled' => false,
            ]);
        } catch (\DomainException $e) {
            // cancel() is idempotent for CANCELLED (and DECLINED) status (early return in domain)
            // but throws for other terminal statuses
            return new JsonResponse(
                [
                    'success'   => false,
                    'errors'    => ['global' => [$e->getMessage()]],
                    'errorCode' => 'APPOINTMENT_TERMINAL_STATUS',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }
}
