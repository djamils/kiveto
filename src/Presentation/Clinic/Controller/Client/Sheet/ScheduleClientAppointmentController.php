<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client\Sheet;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Scheduling\Application\Command\ScheduleAppointment\ScheduleAppointment;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Localization\TimeZone;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/clients/{id}/appointments', name: 'clinic_clients_appointment_schedule', methods: ['POST'])]
final class ScheduleClientAppointmentController extends AbstractClientSheetAction
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);

        $clinicId = $this->clinicId();
        $client   = $this->queryBus->ask(new GetClientById($clinicId, $id));

        if (!$client instanceof ClientView) {
            throw $this->createNotFoundException('Client introuvable.');
        }

        $practitioner = $this->trimmed($request, 'practitioner');
        $animalId     = $this->trimmed($request, 'animal_id');
        $startsAt     = $this->startsAtUtc($clinicId, $request);

        $error = match (true) {
            'archived' === $client->status => 'Cette fiche est archivée : désarchivez-la avant de prendre rendez-vous.',
            null === $practitioner         => 'Le praticien est obligatoire.',
            null === $startsAt             => 'La date ou l’heure du rendez-vous est invalide.',
            // The animal id comes from the form: it must be one of this client's, in this clinic.
            null !== $animalId && !$this->ownsAnimal($clinicId, $id, $animalId) => 'Cet animal n’appartient pas à ce client.',
            default                                                             => null,
        };

        if (null !== $error) {
            $this->addFlash('error', $error);

            return $this->backToSheet($id);
        }

        \assert(null !== $practitioner && null !== $startsAt);

        $scheduled = $this->attempt(fn () => $this->commandBus->dispatch(new ScheduleAppointment(
            clinicId: $clinicId,
            practitionerUserId: $practitioner,
            startsAtUtc: $startsAt,
            durationMinutes: $request->request->getInt('duration', 30),
            reason: $this->trimmed($request, 'reason'),
            ownerId: $id,
            animalId: $animalId,
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

    private function ownsAnimal(string $clinicId, string $clientId, string $animalId): bool
    {
        if (1 !== preg_match('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i', $animalId)) {
            return false;
        }

        try {
            $animal = $this->queryBus->ask(new GetAnimalById($clinicId, $animalId));
        } catch (\DomainException) {
            // Unknown in this clinic: the query refuses it the same way it refuses another clinic's animal.
            return false;
        }

        if (!$animal instanceof AnimalView) {
            return false;
        }

        return $animal->primaryOwnerClientId() === $clientId
            || \in_array($clientId, $animal->secondaryOwnerClientIds(), true);
    }
}
