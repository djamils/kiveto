<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling;

use App\Clinic\Application\Query\GetClinic\ClinicDto;
use App\Clinic\Application\Query\GetClinic\GetClinic;
use App\Scheduling\Application\Query\GetAgendaForClinicDay\GetAgendaForClinicDay;
use App\Scheduling\Application\Query\ListWaitingRoom\ListWaitingRoom;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/scheduling/dashboard', name: 'clinic_scheduling_dashboard', methods: ['GET'])]
final class DashboardController extends AbstractController
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

        // Get current date or requested date
        $dateParam    = $request->query->get('date');
        $selectedDate = $dateParam
            ? new \DateTimeImmutable($dateParam)
            : $this->clock->now();

        // Get today's agenda
        $appointments = $this->queryBus->ask(new GetAgendaForClinicDay(
            clinicId: $currentClinicId->toString(),
            date: $selectedDate,
            practitionerUserId: null, // All practitioners
        ));

        // Get waiting room
        $waitingRoomEntries = $this->queryBus->ask(new ListWaitingRoom(
            clinicId: $currentClinicId->toString(),
        ));

        // Get clinic info
        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        \assert($clinic instanceof ClinicDto);

        return $this->render('clinic/scheduling/dashboard_layout15.html.twig', [
            'appointments'       => $appointments,
            'waitingRoomEntries' => $waitingRoomEntries,
            'selectedDate'       => $selectedDate,
            'currentClinicId'    => $currentClinicId->toString(),
            'currentClinicName'  => $clinic->name,
        ]);
    }
}
