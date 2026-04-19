<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\Planning;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange\ListPlanningBlocksForClinicDateRange;
use App\Context\Scheduling\Application\Query\ListPlanningBlocksForClinicDateRange\PlanningBlockView;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PlanningController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route(path: '/scheduling/planning', name: 'clinic_scheduling_planning', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $user = $this->getUser();
        \assert($user instanceof SecurityUser);

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        \assert($clinic instanceof ClinicDto);

        $clinicTz = TimeZone::fromString($clinic->timeZone)->toNative();

        $dateParam    = $request->query->get('date');
        $selectedDate = $this->clock->now()->setTimezone($clinicTz);
        if (\is_string($dateParam) && 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)) {
            try {
                $selectedDate = new \DateTimeImmutable($dateParam, $clinicTz);
            } catch (\Exception) {
                // Malformed date — silently fall back to today.
            }
        }

        $weekStart = $selectedDate->modify('monday this week')->setTime(0, 0, 0);
        $weekEnd   = $weekStart->modify('+6 days')->setTime(23, 59, 59);

        $planningBlocks = $this->queryBus->ask(new ListPlanningBlocksForClinicDateRange(
            clinicId: $currentClinicId->toString(),
            fromDate: $weekStart->format('Y-m-d'),
            toDate: $weekEnd->format('Y-m-d'),
        ));
        \assert(\is_array($planningBlocks));

        $planningBlocksJs = array_map(static function (mixed $b): array {
            \assert($b instanceof PlanningBlockView);

            return [
                'id'         => $b->id,
                'vet'        => $b->staffUserId,
                'date'       => $b->date,
                'start'      => $b->startTime,
                'end'        => $b->endTime,
                'type'       => $b->type,
                'capacity'   => $b->capacityPerHour,
                'note'       => $b->note ?? '',
                'recurrence' => strtolower($b->recurrenceFreq),
            ];
        }, $planningBlocks);

        $veterinarians = $this->queryBus->ask(
            new ListClinicVeterinarians($currentClinicId->toString()),
        );
        \assert(\is_array($veterinarians));

        $practitionersByUserId = [];
        $vetsJs                = [];
        foreach ($veterinarians as $vet) {
            \assert($vet instanceof ClinicVeterinarianItem);
            if (null !== $vet->displayName) {
                $lowerUserId                         = strtolower($vet->userId);
                $practitionersByUserId[$lowerUserId] = $vet;
                $color                               = $vet->agendaColor ?? '#64748b';
                $vetsJs[$lowerUserId]                = [
                    'name'  => $vet->displayName,
                    'color' => $color,
                    'bg'    => $color . '1a',
                ];
            }
        }

        $prevDate = $selectedDate->modify('-7 days');
        $nextDate = $selectedDate->modify('+7 days');

        return $this->render('clinic/scheduling/planning/index.html.twig', [
            'currentClinicId'       => $currentClinicId->toString(),
            'currentClinicName'     => $clinic->name,
            'currentUserId'         => $user->id(),
            'veterinarians'         => $veterinarians,
            'practitionersByUserId' => $practitionersByUserId,
            'vetsJs'                => $vetsJs,
            'selectedDate'          => $selectedDate,
            'clinicTimezone'        => $clinic->timeZone,
            'planningBlocksJs'      => $planningBlocksJs,
            'prevLink'              => $this->generateUrl('clinic_scheduling_planning', ['date' => $prevDate->format('Y-m-d')]),
            'nextLink'              => $this->generateUrl('clinic_scheduling_planning', ['date' => $nextDate->format('Y-m-d')]),
            'todayLink'             => $this->generateUrl('clinic_scheduling_planning'),
        ]);
    }
}
