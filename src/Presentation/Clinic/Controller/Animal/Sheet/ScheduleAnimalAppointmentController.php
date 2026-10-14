<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal\Sheet;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Scheduling\Application\Command\ScheduleAppointment\ScheduleAppointment;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Localization\TimeZone;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/animals/{id}/appointments', name: 'clinic_animals_appointment_schedule', methods: ['POST'])]
final class ScheduleAnimalAppointmentController extends AbstractAnimalSheetAction
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);

        $animal = $this->animal($id);

        if (!$this->isOpen($animal)) {
            return $this->refuseClosedFile($id);
        }

        $practitioner = $this->trimmed($request, 'practitioner');
        $startsAt     = $this->startsAtUtc($animal->clinicId, $request);

        if (null === $practitioner || null === $startsAt) {
            $this->addFlash('error', 'Le praticien, la date et l’heure du rendez-vous sont obligatoires.');

            return $this->backToSheet($id);
        }

        $scheduled = $this->attempt(fn () => $this->commandBus->dispatch(new ScheduleAppointment(
            clinicId: $animal->clinicId,
            practitionerUserId: $practitioner,
            startsAtUtc: $startsAt,
            durationMinutes: $request->request->getInt('duration', 30),
            reason: $this->trimmed($request, 'reason'),
            ownerId: $animal->primaryOwnerClientId(),
            animalId: $animal->id,
        )));

        if ($scheduled) {
            $this->addFlash('success', 'Rendez-vous créé.');
        } else {
            $this->addFlash(
                'error',
                'Le rendez-vous n’a pas pu être créé : le créneau est déjà pris ou hors des plages du praticien.',
            );
        }

        return $this->backToSheet($id);
    }

    /**
     * The form is filled in clinic time; the agenda stores UTC.
     */
    private function startsAtUtc(string $clinicId, Request $request): ?\DateTimeImmutable
    {
        $date = $this->trimmed($request, 'date');
        $time = $this->trimmed($request, 'time') ?? '09:00';

        if (null === $date || 1 !== preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $date . ' ' . $time)) {
            return null;
        }

        $clinic = $this->queryBus->ask(new GetClinic($clinicId));
        \assert($clinic instanceof ClinicDto);

        $startsAt = \DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i',
            $date . ' ' . $time,
            TimeZone::fromString($clinic->timeZone)->toNative(),
        );

        return false === $startsAt ? null : $startsAt->setTimezone(new \DateTimeZone('UTC'));
    }
}
