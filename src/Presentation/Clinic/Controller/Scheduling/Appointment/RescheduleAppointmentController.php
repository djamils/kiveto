<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\Appointment;

use App\Context\Scheduling\Application\Command\RescheduleAppointment\RescheduleAppointment;
use App\Context\Scheduling\Application\Query\GetAppointmentClinicId\GetAppointmentClinicId;
use App\Context\Scheduling\Application\Query\GetAppointmentDetails\AppointmentDetails;
use App\Context\Scheduling\Application\Query\GetAppointmentDetails\GetAppointmentDetails;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route(
    '/scheduling/appointments/{appointmentId}/reschedule',
    name: 'clinic_scheduling_appointment_reschedule',
    methods: ['POST'],
)]
final class RescheduleAppointmentController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly Connection $connection,
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

        // Multi-tenant guard (defense-in-depth; handler also verifies)
        $appointmentClinicId = $this->queryBus->ask(new GetAppointmentClinicId($appointmentId));
        if (null === $appointmentClinicId || $appointmentClinicId !== $currentClinicId->toString()) {
            throw $this->createNotFoundException();
        }

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

        try {
            $startsAt = new \DateTimeImmutable($startsAtRaw);
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

        try {
            $this->commandBus->dispatch(new RescheduleAppointment(
                clinicId: $currentClinicId->toString(),
                appointmentId: $appointmentId,
                startsAtUtc: $startsAt,
                durationMinutes: $request->request->getInt('durationMinutes', 30),
                practitionerUserId: $practitionerUserId,
            ));

            $details = $this->queryBus->ask(new GetAppointmentDetails($appointmentId));
            \assert($details instanceof AppointmentDetails);

            $labels = $this->fetchLabels($details->ownerId, $details->animalId);

            return new JsonResponse([
                'success'     => true,
                'appointment' => [
                    'id'                 => $details->id,
                    'startsAtUtc'        => $details->startsAtUtc,
                    'durationMinutes'    => $details->durationMinutes,
                    'practitionerUserId' => $details->practitionerUserId,
                    'ownerId'            => $details->ownerId,
                    'animalId'           => $details->animalId,
                    'ownerLabel'         => $labels['ownerLabel'],
                    'animalLabel'        => $labels['animalLabel'],
                    'animalSpecies'      => $labels['animalSpecies'],
                    'practitionerLabel'  => null,
                    'status'             => $details->status,
                    'reason'             => $details->reason,
                    'notes'              => $details->notes,
                ],
            ]);
        } catch (\DomainException $e) {
            $errorCode = match (true) {
                str_contains($e->getMessage(), 'overlapping') => 'APPOINTMENT_CONFLICT',
                str_contains($e->getMessage(), 'terminated')  => 'APPOINTMENT_TERMINAL_STATUS',
                default                                       => 'VALIDATION_FAILED',
            };

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

    /**
     * @return array{ownerLabel: ?string, animalLabel: ?string, animalSpecies: ?string}
     */
    private function fetchLabels(?string $ownerId, ?string $animalId): array
    {
        $ownerLabel    = null;
        $animalLabel   = null;
        $animalSpecies = null;

        if (null !== $ownerId) {
            /** @var false|array{label: string} $row */
            $row = $this->connection->fetchAssociative(
                "SELECT CONCAT(last_name, ' ', first_name) as label FROM client__clients WHERE id = UUID_TO_BIN(:id)",
                ['id' => $ownerId],
            );
            if (false !== $row) {
                $ownerLabel = $row['label'];
            }
        }

        if (null !== $animalId) {
            /** @var false|array{name: string, species: string} $row */
            $row = $this->connection->fetchAssociative(
                'SELECT name, species FROM animal__animals WHERE id = UUID_TO_BIN(:id)',
                ['id' => $animalId],
            );
            if (false !== $row) {
                $animalLabel   = $row['name'];
                $animalSpecies = $row['species'];
            }
        }

        return [
            'ownerLabel'    => $ownerLabel,
            'animalLabel'   => $animalLabel,
            'animalSpecies' => $animalSpecies,
        ];
    }
}
