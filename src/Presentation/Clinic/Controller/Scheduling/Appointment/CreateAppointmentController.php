<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\Appointment;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Scheduling\Application\Command\ScheduleAppointment\ScheduleAppointment;
use App\Context\Scheduling\Application\Query\GetAppointmentDetails\AppointmentDetails;
use App\Context\Scheduling\Application\Query\GetAppointmentDetails\GetAppointmentDetails;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Domain\Localization\TimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/scheduling/appointments/create', name: 'clinic_scheduling_appointment_create', methods: ['POST'])]
final class CreateAppointmentController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
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

        $practitionerUserId = $request->request->getString('practitionerUserId');
        if ('' === $practitionerUserId) {
            return new JsonResponse(
                [
                    'success'   => false,
                    'errors'    => ['practitionerUserId' => ['Le praticien est obligatoire.']],
                    'errorCode' => 'VALIDATION_FAILED',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $startsAtRaw = $request->request->getString('startsAtUtc');
        if ('' === $startsAtRaw) {
            return new JsonResponse(
                [
                    'success'   => false,
                    'errors'    => ['startsAtUtc' => ['La date est obligatoire.']],
                    'errorCode' => 'VALIDATION_FAILED',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $clinicDto = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        \assert($clinicDto instanceof ClinicDto);
        $clinicTz = TimeZone::fromString($clinicDto->timeZone)->toNative();

        try {
            $startsAt = (new \DateTimeImmutable($startsAtRaw, $clinicTz))
                ->setTimezone(new \DateTimeZone('UTC'))
            ;
        } catch (\Exception) {
            return new JsonResponse(
                [
                    'success'   => false,
                    'errors'    => ['startsAtUtc' => ['Format de date invalide.']],
                    'errorCode' => 'VALIDATION_FAILED',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $reason = $request->request->getString('reason') ?: null;
        $notes  = $request->request->getString('notes') ?: null;

        try {
            $appointmentId = $this->commandBus->dispatch(new ScheduleAppointment(
                clinicId: $currentClinicId->toString(),
                practitionerUserId: $practitionerUserId,
                startsAtUtc: $startsAt,
                durationMinutes: $request->request->getInt('durationMinutes', 30),
                reason: $reason,
                notes: $notes,
            ));

            \assert(\is_string($appointmentId));

            $details = $this->queryBus->ask(new GetAppointmentDetails($appointmentId));
            \assert($details instanceof AppointmentDetails);

            return new JsonResponse([
                'success'     => true,
                'appointment' => [
                    'id'                 => $details->id,
                    'startsAtUtc'        => $details->startsAtUtc,
                    'durationMinutes'    => $details->durationMinutes,
                    'practitionerUserId' => $details->practitionerUserId,
                    'linkedAdmissionId'  => $details->linkedAdmissionId,
                    'practitionerLabel'  => null,
                    'status'             => $details->status,
                    'reason'             => $details->reason,
                    'notes'              => $details->notes,
                ],
            ]);
        } catch (\DomainException $e) {
            $errorCode = str_contains($e->getMessage(), 'overlapping')
                ? 'APPOINTMENT_CONFLICT'
                : 'VALIDATION_FAILED';

            return new JsonResponse(
                ['success' => false, 'errors' => ['global' => [$e->getMessage()]], 'errorCode' => $errorCode],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ['success' => false, 'errors' => ['global' => [$e->getMessage()]], 'errorCode' => 'VALIDATION_FAILED'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
    }
}
