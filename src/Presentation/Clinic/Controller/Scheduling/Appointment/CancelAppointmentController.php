<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\Appointment;

use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Context\Scheduling\Application\Query\GetAppointmentClinicId\GetAppointmentClinicId;
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

        try {
            $this->commandBus->dispatch(new CancelAppointment(appointmentId: $appointmentId));

            return new JsonResponse([
                'success'          => true,
                'appointmentId'    => $appointmentId,
                'alreadyCancelled' => false,
            ]);
        } catch (\DomainException $e) {
            // cancel() is idempotent for CANCELLED status (early return in domain)
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
