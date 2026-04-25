<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\WaitingRoom;

use App\Context\Scheduling\Application\Query\GetWaitingRoomEntryDetails\GetWaitingRoomEntryDetails;
use App\Context\Scheduling\Application\Query\GetWaitingRoomEntryDetails\WaitingRoomEntryDetailsDTO;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/scheduling/waiting-room/{entryId}/details',
    name: 'clinic_scheduling_waitingroom_entry_details',
    methods: ['GET'],
)]
final class WaitingRoomEntryDetailsController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(string $entryId): Response
    {
        try {
            $entry = $this->queryBus->ask(new GetWaitingRoomEntryDetails($entryId));
            \assert($entry instanceof WaitingRoomEntryDetailsDTO);
        } catch (HandlerFailedException $e) {
            foreach ($e->getWrappedExceptions() as $nested) {
                if ($nested instanceof \DomainException) {
                    throw $this->createNotFoundException();
                }
            }
            throw $e;
        }

        return $this->render('clinic/scheduling/waiting-room/_entry_details.html.twig', [
            'entry' => $entry,
        ]);
    }
}
