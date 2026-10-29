<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal\Sheet;

use App\Context\Animal\Application\Command\UpdateAnimalLifeCycle\UpdateAnimalLifeCycle;
use App\Context\Animal\Domain\ValueObject\BodyDisposal;
use App\Context\Animal\Domain\ValueObject\DeathCause;
use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Context\Scheduling\Application\Query\ListAppointmentsForAnimal\AnimalAppointmentItem;
use App\Context\Scheduling\Application\Query\ListAppointmentsForAnimal\ListAppointmentsForAnimal;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Déclarer le décès »: the date, the circumstances and what became of the
 * body. The appointments still to come are cancelled, without a message to
 * the owner; the file then turns read-only, nothing is deleted.
 */
#[Route('/animals/{id}/death', name: 'clinic_animals_death_declare', methods: ['POST'])]
final class DeclareAnimalDeathController extends AbstractAnimalSheetAction
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);

        $animal = $this->animal($id);

        if (!$this->isOpen($animal)) {
            return $this->refuseClosedFile($id);
        }

        $deceasedOn = $this->trimmed($request, 'deceased_on');
        $cause      = DeathCause::tryFrom($request->request->getString('cause'));
        $disposal   = BodyDisposal::tryFrom($request->request->getString('body'));

        // One day of slack: the clinic's "today" can be a day ahead of the server's.
        $latest = $this->clock->now()->modify('+1 day')->format('Y-m-d');

        if (!$this->isDate($deceasedOn) || $deceasedOn > $latest) {
            $this->addFlash('error', 'Indiquez une date de décès valide, qui ne soit pas future.');

            return $this->backToSheet($id);
        }

        $declared = $this->attempt(fn () => $this->commandBus->dispatch(new UpdateAnimalLifeCycle(
            clinicId: $animal->clinicId,
            animalId: $animal->id,
            lifeStatus: 'deceased',
            deceasedAt: $deceasedOn,
            missingSince: null,
            deathCause: $cause?->value,
            bodyDisposal: $disposal?->value,
        )));

        if (!$declared) {
            $this->addFlash('error', 'Le décès n’a pas pu être enregistré : vérifiez la date.');

            return $this->backToSheet($id);
        }

        $cancelled = $this->cancelUpcomingAppointments($animal->clinicId, $animal->id);

        $this->addFlash('success', 0 === $cancelled
            ? \sprintf('Décès de %s enregistré.', $animal->name)
            : \sprintf('Décès de %s enregistré · %d rendez-vous annulé%s.', $animal->name, $cancelled, $cancelled > 1 ? 's' : ''));

        return $this->backToSheet($id, 'head');
    }

    private function cancelUpcomingAppointments(string $clinicId, string $animalId): int
    {
        $appointments = $this->queryBus->ask(new ListAppointmentsForAnimal($clinicId, $animalId));
        \assert(\is_array($appointments));

        $now       = $this->clock->now();
        $cancelled = 0;

        foreach ($appointments as $appointment) {
            \assert($appointment instanceof AnimalAppointmentItem);

            $upcoming = 'PLANNED' === $appointment->status
                && new \DateTimeImmutable($appointment->startsAtUtc, new \DateTimeZone('UTC')) >= $now;

            if ($upcoming && $this->attempt(fn () => $this->commandBus->dispatch(new CancelAppointment($appointment->id, $clinicId, notifyClient: false)))) {
                ++$cancelled;
            }
        }

        return $cancelled;
    }
}
