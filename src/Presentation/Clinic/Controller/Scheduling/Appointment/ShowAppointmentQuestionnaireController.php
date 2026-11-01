<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\Appointment;

use App\Presentation\Clinic\ViewModel\PortalRequest\QuestionnaireView;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * The owner's pre-visit questionnaire of an appointment, loaded by the agenda
 * popover of a card that carries the badge. 404 when there is none (or the
 * appointment is another clinic's).
 */
#[Route(
    '/clinic/agenda/appointments/{id}/questionnaire',
    name: 'clinic_scheduling_appointment_questionnaire',
    methods: ['GET'],
)]
final readonly class ShowAppointmentQuestionnaireController
{
    public function __construct(
        private QuestionnaireView $questionnaires,
        private CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(string $id): JsonResponse
    {
        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        // No route requirement: the agenda builds this URL from a `__ID__` placeholder.
        $questionnaire = Uuid::isValid($id) ? $this->questionnaires->of($clinicId->toString(), $id) : null;

        if (null === $questionnaire) {
            return new JsonResponse(['error' => 'Aucun questionnaire pour ce rendez-vous.'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($questionnaire);
    }
}
