<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Admission\Application\Port\AdmissionReadRepositoryInterface;
use App\Context\Admission\Application\Port\WaitingRoomItemDto;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\AppointmentItem;
use App\Context\Scheduling\Application\Query\GetAgendaForClinicDateRange\GetAgendaForClinicDateRange;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/admission/queue', name: 'clinic_admission_queue', methods: ['GET'])]
final class AdmissionQueueController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly AdmissionReadRepositoryInterface $admissionReadRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId = $currentClinicId->toString();

        // ── Active admissions (all locations) ──────────────────────────────
        $entries = $this->admissionReadRepository->findAllActiveForClinic($clinicId);

        $countUnidentified = 0;
        foreach ($entries as $entry) {
            if (!$entry->isPatientIdentifiedAtOpening) {
                ++$countUnidentified;
            }
        }

        // ── Practitioner roster (real) ─────────────────────────────────────
        $veterinarians = $this->queryBus->ask(new ListClinicVeterinarians($clinicId));
        \assert(\is_array($veterinarians));

        /** @var list<ClinicVeterinarianItem> $veterinarians */
        $practitioners = $veterinarians;

        // ── Today's scheduled appointments (real) ─────────────────────────
        // Timezone: Europe/Paris as sensible default — per-clinic TZ wiring is a V2 TODO
        $clinicTz     = new \DateTimeZone('Europe/Paris');
        $today        = new \DateTimeImmutable('now', $clinicTz);
        $appointments = $this->queryBus->ask(
            GetAgendaForClinicDateRange::forDay($clinicId, $today, $clinicTz),
        );
        \assert(\is_array($appointments));

        $plannedAppointments = [];
        foreach ($appointments as $appt) {
            \assert($appt instanceof AppointmentItem);
            if ('PLANNED' === $appt->status && null === $appt->linkedAdmissionId) {
                $plannedAppointments[] = $appt;
            }
        }

        // ── Animal enrichment (species, breed, owner name/phone) ───────────
        $animalEnrichment = $this->enrichFromSearchEntries($entries, $clinicId);

        return $this->render('clinic/admission/queue.html.twig', [
            'entries'           => $entries,
            'countUnidentified' => $countUnidentified,
            'practitioners'     => $practitioners,
            'appointments'      => $plannedAppointments,
            'animalEnrichment'  => $animalEnrichment,
        ]);
    }

    /**
     * Batch-enriches admission entries with animal species/breed, age, last weight,
     * and owner name/phone. Runs at most 4 DBAL queries regardless of entry count.
     *
     * @param list<WaitingRoomItemDto> $entries
     *
     * @return array<string, array{species:string|null,breed:string|null,age:string|null,weight:string|null,ownerName:string|null,ownerPhone:string|null}>
     */
    private function enrichFromSearchEntries(array $entries, string $clinicId): array
    {
        $animalIds = [];
        foreach ($entries as $entry) {
            if (null !== $entry->knownAnimalId) {
                $animalIds[$entry->admissionId] = $entry->knownAnimalId;
            }
        }

        if ([] === $animalIds) {
            return [];
        }

        $conn         = $this->entityManager->getConnection();
        $clinicBinary = Uuid::fromString($clinicId)->toBinary();

        $binAnimalIds = array_map(
            static fn (string $id): string => Uuid::fromString($id)->toBinary(),
            array_values($animalIds),
        );

        // Query search entries + animal birthdate in one JOIN
        $searchRows = $conn->fetchAllAssociative(
            'SELECT BIN_TO_UUID(se.id) AS animal_id, se.species, se.breed_name,
                    se.search_owner_name, BIN_TO_UUID(se.primary_owner_client_id) AS owner_client_id,
                    a.birth_date
             FROM animal__search_entries se
             LEFT JOIN animal__animals a ON a.id = se.id
             WHERE se.clinic_id = ? AND se.id IN (?)',
            [$clinicBinary, $binAnimalIds],
            [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ArrayParameterType::STRING],
        );

        /** @var array<string, array{species:string|null,breed:string|null,age:string|null,weight:string|null,ownerName:string|null,ownerPhone:string|null,ownerClientId:string|null}> $byAnimalId */
        $byAnimalId = [];
        $clientIds  = [];

        foreach ($searchRows as $row) {
            \assert(\is_string($row['animal_id']));
            $age = null;
            if (\is_string($row['birth_date']) && '' !== $row['birth_date']) {
                $age = $this->computeAge(new \DateTimeImmutable($row['birth_date']));
            }

            $byAnimalId[$row['animal_id']] = [
                'species'       => \is_string($row['species']) ? $row['species'] : null,
                'breed'         => \is_string($row['breed_name']) ? $row['breed_name'] : null,
                'age'           => $age,
                'weight'        => null,
                'ownerName'     => \is_string($row['search_owner_name']) ? $row['search_owner_name'] : null,
                'ownerPhone'    => null,
                'ownerClientId' => \is_string($row['owner_client_id']) ? $row['owner_client_id'] : null,
            ];

            if (\is_string($row['owner_client_id'])) {
                $clientIds[] = Uuid::fromString($row['owner_client_id'])->toBinary();
            }
        }

        // Last recorded weight per animal via patient__patients → consultation__consultations
        $weightRows = $conn->fetchAllAssociative(
            'SELECT BIN_TO_UUID(pp.animal_link_id) AS animal_id, c.weight_kg
             FROM consultation__consultations c
             INNER JOIN patient__patients pp ON pp.id = c.patient_id
             WHERE pp.animal_link_id IN (?) AND c.weight_kg IS NOT NULL
             ORDER BY c.started_at_utc DESC',
            [$binAnimalIds],
            [\Doctrine\DBAL\ArrayParameterType::STRING],
        );

        foreach ($weightRows as $wr) {
            \assert(\is_string($wr['animal_id']));
            if (isset($byAnimalId[$wr['animal_id']]) && null === $byAnimalId[$wr['animal_id']]['weight']) {
                \assert(\is_string($wr['weight_kg']) || \is_float($wr['weight_kg']) || \is_int($wr['weight_kg']));
                $byAnimalId[$wr['animal_id']]['weight'] = $this->formatWeight($wr['weight_kg']);
            }
        }

        // Fetch primary phone for each owner client
        if ([] !== $clientIds) {
            $phoneRows = $conn->fetchAllAssociative(
                "SELECT BIN_TO_UUID(client_id) AS client_id, value AS phone
                 FROM client__contact_methods
                 WHERE client_id IN (?) AND type = 'phone' AND is_primary = 1",
                [$clientIds],
                [\Doctrine\DBAL\ArrayParameterType::STRING],
            );

            /** @var array<string, string> $phoneByClient */
            $phoneByClient = [];
            foreach ($phoneRows as $pr) {
                \assert(\is_string($pr['client_id']));
                \assert(\is_string($pr['phone']));
                $phoneByClient[$pr['client_id']] = $pr['phone'];
            }

            foreach ($byAnimalId as &$data) {
                $cid = $data['ownerClientId'] ?? null;
                if (\is_string($cid) && isset($phoneByClient[$cid])) {
                    $data['ownerPhone'] = $phoneByClient[$cid];
                }
            }
            unset($data);
        }

        // Map by admission ID
        $result = [];
        foreach ($animalIds as $admissionId => $animalId) {
            if (isset($byAnimalId[$animalId])) {
                $d                    = $byAnimalId[$animalId];
                $result[$admissionId] = [
                    'species'    => $d['species'],
                    'breed'      => $d['breed'],
                    'age'        => $d['age'],
                    'weight'     => $d['weight'],
                    'ownerName'  => $d['ownerName'],
                    'ownerPhone' => $d['ownerPhone'],
                ];
            }
        }

        return $result;
    }

    private function computeAge(\DateTimeImmutable $birthDate): string
    {
        $diff = $birthDate->diff(new \DateTimeImmutable('now'));

        if ($diff->y >= 1) {
            return $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
        }

        $months = $diff->m + ($diff->y * 12);
        if ($months >= 1) {
            return $months . ' mois';
        }

        return '< 1 mois';
    }

    private function formatWeight(string|float|int $raw): string
    {
        $kg        = (float) $raw;
        $formatted = rtrim(rtrim(number_format($kg, 3, ',', ''), '0'), ',');

        return $formatted . ' kg';
    }
}
