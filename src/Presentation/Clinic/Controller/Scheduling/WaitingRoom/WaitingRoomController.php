<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\WaitingRoom;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Scheduling\Application\Query\ListWaitingRoom\ListWaitingRoom;
use App\Context\Scheduling\Application\Query\ListWaitingRoom\WaitingRoomEntryItem;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class WaitingRoomController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route(path: '/scheduling/waiting-room', name: 'clinic_scheduling_waiting_room', methods: ['GET'])]
    public function __invoke(): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        \assert($clinic instanceof ClinicDto);

        $clinicTz    = TimeZone::fromString($clinic->timeZone)->toNative();
        $formatter   = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, $clinicTz);
        $currentDate = ucfirst((string) $formatter->format($this->clock->now()));

        $rawEntries = $this->queryBus->ask(new ListWaitingRoom($currentClinicId->toString()));
        \assert(\is_array($rawEntries));
        /** @var WaitingRoomEntryItem[] $entries */
        $entries = $rawEntries;

        $countWaiting   = \count(array_filter($entries, static fn (WaitingRoomEntryItem $e) => 'WAITING' === $e->status));
        $countService   = \count(array_filter($entries, static fn (WaitingRoomEntryItem $e) => 'IN_SERVICE' === $e->status));
        $countEmergency = \count(array_filter($entries, static fn (WaitingRoomEntryItem $e) => 'EMERGENCY' === $e->arrivalMode));

        return $this->render('clinic/scheduling/waiting-room/index.html.twig', [
            'currentDate'       => $currentDate,
            'currentClinicId'   => $currentClinicId->toString(),
            'currentClinicName' => $clinic->name,
            'countWaiting'      => $countWaiting,
            'countService'      => $countService,
            'countEmergency'    => $countEmergency,
        ]);
    }
}
