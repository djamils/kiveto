<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling;

use App\Clinic\Application\Query\GetClinic\ClinicDto;
use App\Clinic\Application\Query\GetClinic\GetClinic;
use App\Scheduling\Application\Query\ListWaitingRoom\ListWaitingRoom;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WaitingRoomController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    #[Route(path: '/scheduling/waiting-room', name: 'clinic_scheduling_waiting_room', methods: ['GET'])]
    public function __invoke(): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        \assert($clinic instanceof ClinicDto);

        $waitingRoomEntries = $this->queryBus->ask(new ListWaitingRoom($currentClinicId->toString()));
        \assert(\is_array($waitingRoomEntries));

        return $this->render('clinic/scheduling/waiting-room/index.html.twig', [
            'waitingRoomEntries' => $waitingRoomEntries,
            'currentClinicId'   => $currentClinicId->toString(),
            'currentClinicName' => $clinic->name,
        ]);
    }
}
