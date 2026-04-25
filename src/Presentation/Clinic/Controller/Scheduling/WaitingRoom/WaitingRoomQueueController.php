<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\WaitingRoom;

use App\Context\Scheduling\Application\Query\ListWaitingRoom\ListWaitingRoom;
use App\Context\Scheduling\Application\Query\ListWaitingRoom\WaitingRoomEntryItem;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/scheduling/waiting-room/queue',
    name: 'clinic_scheduling_waitingroom_queue',
    methods: ['GET'],
)]
final class WaitingRoomQueueController extends AbstractController
{
    private const VALID_FILTERS = ['all', 'urgence', 'attente', 'consultation'];

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $filter = $request->query->getString('filter', 'all');
        if (!\in_array($filter, self::VALID_FILTERS, true)) {
            $filter = 'all';
        }

        $entries = $this->queryBus->ask(new ListWaitingRoom($currentClinicId->toString()));
        \assert(\is_array($entries));

        /** @var WaitingRoomEntryItem[] $entries */
        $filtered = match ($filter) {
            'urgence'      => array_filter($entries, static fn (WaitingRoomEntryItem $e) => 'EMERGENCY' === $e->arrivalMode),
            'attente'      => array_filter($entries, static fn (WaitingRoomEntryItem $e) => 'WAITING' === $e->status),
            'consultation' => array_filter($entries, static fn (WaitingRoomEntryItem $e) => 'IN_SERVICE' === $e->status),
            default        => $entries,
        };

        return $this->render('clinic/scheduling/waiting-room/_queue_frame.html.twig', [
            'entries'       => array_values($filtered),
            'currentFilter' => $filter,
        ]);
    }
}
