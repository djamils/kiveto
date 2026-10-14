<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Billing;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * What an invoice is about, read from the visit it charges: the animal, the
 * practitioner and the consultation's motifs, its ordonnances — and the other
 * way round, the visits a search term or a practitioner filter points at.
 *
 * An invoice only knows its visit (admission); everything else lives in the
 * Admission, Patient, Animal, Consultation and Prescription contexts. The
 * register needs it for every row of a page, so it is read here in a few
 * batched queries — the way the dossier pages read the same chain.
 */
final readonly class BillingVisitReader
{
    private const int MAX_MATCHES = 500;

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param list<string> $admissionIds
     *
     * @return array<string, array{
     *     consultationId: ?string, open: bool, vetId: ?string, startedAt: ?string,
     *     motifs: list<string>, complaint: ?string,
     *     animal: array{id: ?string, name: string, species: ?string}
     * }>
     */
    public function visits(array $admissionIds): array
    {
        if ([] === $admissionIds) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(a.id) AS admission_id, BIN_TO_UUID(c.id) AS consultation_id, c.status,
                    BIN_TO_UUID(c.practitioner_user_id) AS vet_id, c.started_at_utc, c.chief_complaint,
                    BIN_TO_UUID(p.animal_link_id) AS animal_id, se.animal_name, se.species, p.display_label_value
             FROM admission__admissions a
             INNER JOIN patient__patients p ON p.id = a.patient_id
             LEFT JOIN animal__search_entries se ON se.id = p.animal_link_id
             LEFT JOIN consultation__consultations c ON c.admission_id = a.id
             WHERE a.id IN (?)
             ORDER BY c.started_at_utc ASC',
            [$this->binaries($admissionIds)],
            [ArrayParameterType::BINARY],
        );

        $visits        = [];
        $consultations = [];

        foreach ($rows as $row) {
            $admission = RowAccessor::string($row, 'admission_id');

            if (isset($visits[$admission])) {
                continue;
            }

            $consultation = RowAccessor::nullableString($row, 'consultation_id');

            if (null !== $consultation) {
                $consultations[$consultation] = $admission;
            }

            $visits[$admission] = [
                'consultationId' => $consultation,
                'open'           => 'OPEN' === RowAccessor::nullableString($row, 'status'),
                'vetId'          => RowAccessor::nullableString($row, 'vet_id'),
                'startedAt'      => RowAccessor::nullableString($row, 'started_at_utc'),
                'motifs'         => [],
                'complaint'      => RowAccessor::nullableString($row, 'chief_complaint'),
                'animal'         => [
                    'id'      => RowAccessor::nullableString($row, 'animal_id'),
                    'name'    => RowAccessor::nullableString($row, 'animal_name') ?? RowAccessor::nullableString($row, 'display_label_value') ?? 'Animal non identifié',
                    'species' => RowAccessor::nullableString($row, 'species'),
                ],
            ];
        }

        if ([] !== $consultations) {
            $motifs = $this->connection->fetchAllAssociative(
                'SELECT BIN_TO_UUID(consultation_id) AS consultation_id, label FROM consultation__motifs
                 WHERE consultation_id IN (?) ORDER BY position ASC',
                [$this->binaries(array_keys($consultations))],
                [ArrayParameterType::BINARY],
            );

            $labels = [];

            foreach ($motifs as $motif) {
                $labels[$consultations[RowAccessor::string($motif, 'consultation_id')]][] = RowAccessor::string($motif, 'label');
            }

            foreach ($labels as $admission => $list) {
                if (isset($visits[$admission])) {
                    $visits[$admission]['motifs'] = $list;
                }
            }
        }

        return $visits;
    }

    /**
     * Signed ordonnances of these consultations.
     *
     * @param list<string> $consultationIds
     *
     * @return array<string, list<array{id: string, number: string}>> consultation id => ordonnances
     */
    public function prescriptions(array $consultationIds): array
    {
        if ([] === $consultationIds) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(id) AS id, BIN_TO_UUID(consultation_id) AS consultation_id, number
             FROM prescription__prescriptions
             WHERE consultation_id IN (?) AND status = 'ISSUED' AND number IS NOT NULL
             ORDER BY issued_at ASC",
            [$this->binaries($consultationIds)],
            [ArrayParameterType::BINARY],
        );

        $list = [];

        foreach ($rows as $row) {
            $list[RowAccessor::string($row, 'consultation_id')][] = [
                'id'     => RowAccessor::string($row, 'id'),
                'number' => RowAccessor::string($row, 'number'),
            ];
        }

        return $list;
    }

    /**
     * The clients' main phone number, as the sheet prints it under their name.
     *
     * @param list<string> $clientIds
     *
     * @return array<string, string> client id => phone
     */
    public function phones(array $clientIds): array
    {
        if ([] === $clientIds) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(client_id) AS client_id, value FROM client__contact_methods
             WHERE client_id IN (?) AND type = 'phone'
             ORDER BY is_primary DESC",
            [$this->binaries($clientIds)],
            [ArrayParameterType::BINARY],
        );

        $phones = [];

        foreach ($rows as $row) {
            $phones[RowAccessor::string($row, 'client_id')] ??= RowAccessor::string($row, 'value');
        }

        return $phones;
    }

    /**
     * The visits a practitioner saw: what the « Praticien » filter keeps.
     *
     * @return list<string>
     */
    public function admissionsOf(string $clinicId, string $practitionerId): array
    {
        return $this->ids(
            'SELECT DISTINCT BIN_TO_UUID(admission_id) FROM consultation__consultations
             WHERE clinic_id = :clinicId AND practitioner_user_id = :vet',
            ['clinicId' => Uuid::fromString($clinicId)->toBinary(), 'vet' => Uuid::fromString($practitionerId)->toBinary()],
        );
    }

    /**
     * The visits whose animal or motif a search term matches, so that
     * « Rocky » or « otite » finds the invoice too.
     *
     * @return list<string>
     */
    public function admissionsMatching(string $clinicId, string $term): array
    {
        if (mb_strlen($term) < 2) {
            return [];
        }

        return $this->ids(
            \sprintf(
                'SELECT DISTINCT BIN_TO_UUID(a.id)
                 FROM admission__admissions a
                 INNER JOIN patient__patients p ON p.id = a.patient_id
                 LEFT JOIN animal__search_entries se ON se.id = p.animal_link_id
                 LEFT JOIN consultation__consultations c ON c.admission_id = a.id
                 WHERE a.clinic_id = :clinicId AND (
                     se.animal_name LIKE :term OR p.display_label_value LIKE :term OR c.chief_complaint LIKE :term
                     OR EXISTS (SELECT 1 FROM consultation__motifs m WHERE m.consultation_id = c.id AND m.label LIKE :term)
                 )
                 LIMIT %d',
                self::MAX_MATCHES,
            ),
            ['clinicId' => Uuid::fromString($clinicId)->toBinary(), 'term' => '%' . $term . '%'],
        );
    }

    /**
     * @param array<string, string> $params
     *
     * @return list<string>
     */
    private function ids(string $sql, array $params): array
    {
        $ids = [];

        foreach ($this->connection->fetchFirstColumn($sql, $params) as $id) {
            if (\is_string($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function binaries(array $ids): array
    {
        return array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $ids);
    }
}
