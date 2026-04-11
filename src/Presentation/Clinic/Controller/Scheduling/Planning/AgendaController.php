<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\Planning;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\GetAgendaForClinicDateRange;
use App\Context\Scheduling\Application\Query\ListWaitingRoom\ListWaitingRoom;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/scheduling/agenda', name: 'clinic_scheduling_agenda', methods: ['GET'])]
final class AgendaController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $user = $this->getUser();
        \assert($user instanceof SecurityUser);

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        \assert($clinic instanceof ClinicDto);

        $clinicTz = TimeZone::fromString($clinic->timeZone)->toNative();

        $viewParam = $request->query->get('view', 'week');
        if (!\in_array($viewParam, ['day', 'week'], true)) {
            $viewParam = 'week';
        }

        $dateParam    = $request->query->get('date');
        $selectedDate = $this->clock->now()->setTimezone($clinicTz);
        if (\is_string($dateParam) && 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
            try {
                $selectedDate = new \DateTimeImmutable($dateParam, $clinicTz);
            } catch (\Exception) {
                // Malformed date (e.g. 2026-13-40) — silently fall back to today.
            }
        }

        $query = 'day' === $viewParam
            ? GetAgendaForClinicDateRange::forDay(
                clinicId: $currentClinicId->toString(),
                date: $selectedDate,
                clinicTz: $clinicTz,
            )
            : GetAgendaForClinicDateRange::forWeek(
                clinicId: $currentClinicId->toString(),
                anchorDate: $selectedDate,
                clinicTz: $clinicTz,
            );

        $appointments = $this->queryBus->ask($query);
        \assert(\is_array($appointments));

        $veterinarians = $this->queryBus->ask(
            new ListClinicVeterinarians($currentClinicId->toString()),
        );
        \assert(\is_array($veterinarians));

        $waitingRoomEntries = $this->queryBus->ask(new ListWaitingRoom(
            clinicId: $currentClinicId->toString(),
        ));

        $navStep  = 'day' === $viewParam ? '1 day' : '7 days';
        $prevDate = $selectedDate->modify('-' . $navStep);
        $nextDate = $selectedDate->modify('+' . $navStep);

        $prevLink = $this->generateUrl('clinic_scheduling_agenda', [
            'date' => $prevDate->format('Y-m-d'),
            'view' => $viewParam,
        ]);
        $nextLink = $this->generateUrl('clinic_scheduling_agenda', [
            'date' => $nextDate->format('Y-m-d'),
            'view' => $viewParam,
        ]);
        $todayLink = $this->generateUrl('clinic_scheduling_agenda', [
            'view' => $viewParam,
        ]);
        $weekViewLink = $this->generateUrl('clinic_scheduling_agenda', [
            'date' => $selectedDate->format('Y-m-d'),
            'view' => 'week',
        ]);
        $dayViewLink = $this->generateUrl('clinic_scheduling_agenda', [
            'date' => $selectedDate->format('Y-m-d'),
            'view' => 'day',
        ]);

        $checkinUrlTemplate = $this->generateUrl('clinic_scheduling_appointment_checkin', [
            'appointmentId' => '__ID__',
        ]);

        return $this->render('clinic/scheduling/agenda/index.html.twig', [
            'appointments'       => $appointments,
            'veterinarians'      => $veterinarians,
            'waitingRoomEntries' => $waitingRoomEntries,
            'selectedDate'       => $selectedDate,
            'view'               => $viewParam,
            'clinicTimezone'     => $clinic->timeZone,
            'currentUserId'      => $user->id(),
            'currentClinicId'    => $currentClinicId->toString(),
            'currentClinicName'  => $clinic->name,
            'prevLink'           => $prevLink,
            'nextLink'           => $nextLink,
            'todayLink'          => $todayLink,
            'weekViewLink'       => $weekViewLink,
            'dayViewLink'        => $dayViewLink,
            'checkinUrlTemplate' => $checkinUrlTemplate,
        ]);
    }
}
