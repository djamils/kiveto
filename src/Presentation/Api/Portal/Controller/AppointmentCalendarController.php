<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Controller;

use App\Context\ClientPortal\Application\Query\GetOwnerAppointmentDetail\GetOwnerAppointmentDetail;
use App\Context\ClientPortal\Application\Query\ListOwnerAppointments\PortalAppointment;
use App\Context\Scheduling\Application\Query\GetAppointmentCalendar\GetAppointmentCalendar;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Shared\Problem\ApiProblem;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The `.ics` of an appointment of the caller (`METHOD:PUBLISH`), once
 * ClientPortal showed it is theirs; Scheduling answers nothing for one that
 * no longer holds its slot (cancelled, declined, completed, no-show).
 */
final readonly class AppointmentCalendarController
{
    public const string FILENAME = 'rendez-vous.ics';

    public function __construct(
        private PortalClinicGuard $guard,
        private QueryBusInterface $queryBus,
    ) {
    }

    #[Route('/clinics/{clinicId}/appointments/{appointmentId}/calendar.ics', name: 'api_portal_appointment_calendar', methods: ['GET'])]
    public function __invoke(string $clinicId, string $appointmentId, #[CurrentUser] PortalApiUser $user): Response
    {
        try {
            $this->guard->require($user->id(), $clinicId);
        } catch (ApiProblem $problem) {
            return PortalProblemMap::response($problem->getType());
        }

        if (!$this->queryBus->ask(new GetOwnerAppointmentDetail($user->id(), $clinicId, $appointmentId)) instanceof PortalAppointment) {
            return PortalProblemMap::response(PortalProblemMap::NOT_FOUND);
        }

        $ics = $this->queryBus->ask(new GetAppointmentCalendar($clinicId, $appointmentId));

        if (!\is_string($ics)) {
            return PortalProblemMap::response(PortalProblemMap::NOT_FOUND);
        }

        return new Response($ics, Response::HTTP_OK, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, self::FILENAME),
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
