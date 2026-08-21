<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\SearchConsultations\ConsultationListRow;
use App\Context\Consultation\Application\Query\SearchConsultations\SearchConsultationsCriteria;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineConsultationReadRepository implements ConsultationReadRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function findById(ConsultationId $consultationId, ClinicId $clinicId): ConsultationDetailsDTO
    {
        $consultationIdBinary = Uuid::fromString($consultationId->toString())->toBinary();
        $clinicIdBinary       = Uuid::fromString($clinicId->toString())->toBinary();

        // Fetch consultation
        $sql          = 'SELECT * FROM consultation__consultations WHERE id = :id AND clinic_id = :clinicId';
        $consultation = $this->connection->fetchAssociative($sql, [
            'id'       => $consultationIdBinary,
            'clinicId' => $clinicIdBinary,
        ]);

        if (false === $consultation) {
            throw new \DomainException(\sprintf(
                'Consultation "%s" not found.',
                $consultationId->toString()
            ));
        }

        // Build vitals array if present
        $weightKg     = RowAccessor::nullableString($consultation, 'weight_kg');
        $temperatureC = RowAccessor::nullableString($consultation, 'temperature_c');
        $vitals       = null;
        if (null !== $weightKg || null !== $temperatureC) {
            $vitals = [
                'weightKg'     => $weightKg,
                'temperatureC' => $temperatureC,
            ];
        }

        return new ConsultationDetailsDTO(
            consultationId: RowAccessor::uuid($consultation, 'id'),
            clinicId: RowAccessor::uuid($consultation, 'clinic_id'),
            practitionerUserId: RowAccessor::uuid($consultation, 'practitioner_user_id'),
            status: RowAccessor::string($consultation, 'status'),
            appointmentId: RowAccessor::nullableUuid($consultation, 'appointment_id'),
            admissionId: RowAccessor::uuid($consultation, 'admission_id'),
            patientId: RowAccessor::uuid($consultation, 'patient_id'),
            chiefComplaint: RowAccessor::nullableString($consultation, 'chief_complaint'),
            vitals: $vitals,
            notes: $this->fetchNotes($consultationIdBinary),
            acts: $this->fetchActs($consultationIdBinary),
            summary: RowAccessor::nullableString($consultation, 'summary'),
            startedAtUtc: RowAccessor::string($consultation, 'started_at_utc'),
            closedAtUtc: RowAccessor::nullableString($consultation, 'closed_at_utc'),
            subjectiveText: RowAccessor::nullableString($consultation, 'subjective_text'),
            objectiveObservations: RowAccessor::nullableString($consultation, 'objective_observations'),
            teamMemo: RowAccessor::nullableString($consultation, 'team_memo'),
            motifs: $this->fetchMotifs($consultationIdBinary),
            typedVitals: $this->fetchTypedVitals($consultationIdBinary),
            examSystems: $this->fetchExamSystems($consultationIdBinary),
            diagnoses: $this->fetchDiagnoses($consultationIdBinary),
            planActions: $this->fetchPlanActions($consultationIdBinary),
            prescriptionLines: $this->fetchPrescriptionLines($consultationIdBinary),
            billingLines: $this->fetchBillingLines($consultationIdBinary),
            // Money maths (VAT, TTC) belongs to the query handler, which owns the
            // Taxation port; this repository stays bus-free.
            totals: [
                'totalHtMinorUnits'  => 0,
                'totalTvaMinorUnits' => 0,
                'totalTtcMinorUnits' => 0,
                'currency'           => '',
            ],
        );
    }

    public function listForPatients(array $patientIds, ClinicId $clinicId, ?ConsultationId $excludeId = null): array
    {
        if ([] === $patientIds) {
            return [];
        }

        $binaryIds = array_map(
            static fn (string $patientId): string => Uuid::fromString($patientId)->toBinary(),
            $patientIds,
        );

        $sql = 'SELECT id, started_at_utc, closed_at_utc, status, chief_complaint, summary, weight_kg
                FROM consultation__consultations
                WHERE clinic_id = :clinicId AND patient_id IN (:patientIds)';

        $params = [
            'clinicId'   => Uuid::fromString($clinicId->toString())->toBinary(),
            'patientIds' => $binaryIds,
        ];
        $types = ['patientIds' => ArrayParameterType::BINARY];

        if (null !== $excludeId) {
            $sql .= ' AND id <> :excludeId';
            $params['excludeId'] = Uuid::fromString($excludeId->toString())->toBinary();
        }

        $sql .= ' ORDER BY started_at_utc DESC';

        $rows = $this->connection->fetchAllAssociative($sql, $params, $types);

        return array_map(
            /**
             * @param array<string, mixed> $row
             *
             * @return array{
             *     consultationId: string,
             *     startedAtUtc: string,
             *     closedAtUtc: ?string,
             *     status: string,
             *     chiefComplaint: ?string,
             *     summary: ?string,
             *     weightKg: ?string
             * }
             */
            static fn (array $row): array => [
                'consultationId' => RowAccessor::uuid($row, 'id'),
                'startedAtUtc'   => RowAccessor::string($row, 'started_at_utc'),
                'closedAtUtc'    => RowAccessor::nullableString($row, 'closed_at_utc'),
                'status'         => RowAccessor::string($row, 'status'),
                'chiefComplaint' => RowAccessor::nullableString($row, 'chief_complaint'),
                'summary'        => RowAccessor::nullableString($row, 'summary'),
                'weightKg'       => RowAccessor::nullableString($row, 'weight_kg'),
            ],
            $rows,
        );
    }

    public function search(ClinicId $clinicId, SearchConsultationsCriteria $criteria): array
    {
        if ($criteria->isImpossible()) {
            return ['items' => [], 'total' => 0];
        }

        $filter = $this->buildSearchFilter($clinicId, $criteria);

        $totalRaw = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM consultation__consultations c WHERE ' . $filter['where'],
            $filter['params'],
            $filter['types'],
        );
        $total = is_numeric($totalRaw) ? (int) $totalRaw : 0;

        if (0 === $total) {
            return ['items' => [], 'total' => 0];
        }

        $order  = $this->buildSearchOrder($criteria, $filter['params'], $filter['types']);
        $params = $filter['params'];
        $types  = $filter['types'];

        // The pre-tax sum is joined for ordering only; the per-category
        // breakdown each row needs is batched separately below.
        $rows = $this->connection->fetchAllAssociative(
            'SELECT c.id, c.patient_id, c.practitioner_user_id, c.status,
                    c.started_at_utc, c.closed_at_utc, c.chief_complaint
             FROM consultation__consultations c
             LEFT JOIN (
                 SELECT consultation_id, SUM(quantity * unit_price_minor_units) AS total_ht
                 FROM consultation__billing_lines GROUP BY consultation_id
             ) bl ON bl.consultation_id = c.id
             WHERE ' . $filter['where'] . '
             ORDER BY ' . $order . '
             LIMIT ' . $criteria->limit . ' OFFSET ' . $criteria->offset(),
            $params,
            $types,
        );

        $binaryIds = array_map(
            /** @param array<string, mixed> $row */
            static fn (array $row): string => RowAccessor::string($row, 'id'),
            $rows,
        );

        $motifs  = $this->fetchMotifsFor($binaryIds);
        $amounts = $this->fetchAmountsFor($binaryIds);

        $items = [];

        foreach ($rows as $row) {
            $binaryId = RowAccessor::string($row, 'id');
            $amount   = $amounts[$binaryId] ?? ['htByTaxCategory' => [], 'currency' => ''];

            $items[] = new ConsultationListRow(
                consultationId: RowAccessor::uuid($row, 'id'),
                patientId: RowAccessor::uuid($row, 'patient_id'),
                practitionerUserId: RowAccessor::uuid($row, 'practitioner_user_id'),
                status: RowAccessor::string($row, 'status'),
                startedAtUtc: RowAccessor::string($row, 'started_at_utc'),
                closedAtUtc: RowAccessor::nullableString($row, 'closed_at_utc'),
                chiefComplaint: RowAccessor::nullableString($row, 'chief_complaint'),
                motifs: $motifs[$binaryId] ?? [],
                htByTaxCategory: $amount['htByTaxCategory'],
                currency: $amount['currency'],
            );
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{
     *     where: string,
     *     params: array<string, mixed>,
     *     types: array<string, ArrayParameterType>
     * }
     */
    private function buildSearchFilter(ClinicId $clinicId, SearchConsultationsCriteria $criteria): array
    {
        $where  = ['c.clinic_id = :clinicId'];
        $params = ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()];
        /** @var array<string, ArrayParameterType> $types */
        $types = [];

        if (null !== $criteria->startedAfterUtc) {
            $where[]                = 'c.started_at_utc >= :startedAfter';
            $params['startedAfter'] = $criteria->startedAfterUtc->format('Y-m-d H:i:s');
        }

        if ([] !== $criteria->statuses) {
            $where[]            = 'c.status IN (:statuses)';
            $params['statuses'] = $criteria->statuses;
            $types['statuses']  = ArrayParameterType::STRING;
        }

        if ([] !== $criteria->practitionerUserIds) {
            $where[]                   = 'c.practitioner_user_id IN (:practitionerIds)';
            $params['practitionerIds'] = self::toBinaryIds($criteria->practitionerUserIds);
            $types['practitionerIds']  = ArrayParameterType::BINARY;
        }

        if (null !== $criteria->restrictToPatientIds) {
            $where[]                      = 'c.patient_id IN (:restrictPatientIds)';
            $params['restrictPatientIds'] = self::toBinaryIds($criteria->restrictToPatientIds);
            $types['restrictPatientIds']  = ArrayParameterType::BINARY;
        }

        $term = $criteria->searchTerm;

        if (null !== $term && '' !== $term) {
            // Free text spans this context's own fields plus the patients the
            // caller matched by animal or owner name.
            $clauses = [
                'c.chief_complaint LIKE :term',
                'c.summary LIKE :term',
                'EXISTS (SELECT 1 FROM consultation__motifs m WHERE m.consultation_id = c.id AND m.label LIKE :term)',
                'EXISTS (SELECT 1 FROM consultation__diagnoses d WHERE d.consultation_id = c.id AND d.label LIKE :term)',
            ];
            $params['term'] = '%' . $term . '%';

            if ([] !== $criteria->textMatchPatientIds) {
                $clauses[]                = 'c.patient_id IN (:textPatientIds)';
                $params['textPatientIds'] = self::toBinaryIds($criteria->textMatchPatientIds);
                $types['textPatientIds']  = ArrayParameterType::BINARY;
            }

            $where[] = '(' . implode(' OR ', $clauses) . ')';
        }

        return ['where' => implode(' AND ', $where), 'params' => $params, 'types' => $types];
    }

    /**
     * Builds the ORDER BY clause from the whitelisted sort column.
     *
     * Ordering on the vet column relies on the caller supplying the
     * practitioners in display order: this context stores a user id and has no
     * idea how a practitioner is named. Ordering on the amount uses the
     * pre-tax sum — with a single VAT rate it matches the displayed total, and
     * it keeps the sort inside the database.
     *
     * @param array<string, mixed>              $params
     * @param array<string, ArrayParameterType> $types
     */
    private function buildSearchOrder(SearchConsultationsCriteria $criteria, array &$params, array &$types): string
    {
        $direction = 'asc' === $criteria->direction ? 'ASC' : 'DESC';

        $column = match ($criteria->sort) {
            SearchConsultationsCriteria::SORT_STATUS => 'c.status',
            SearchConsultationsCriteria::SORT_AMOUNT => 'COALESCE(bl.total_ht, 0)',
            SearchConsultationsCriteria::SORT_VET    => $this->practitionerOrderExpression($criteria, $params, $types),
            default                                  => 'c.started_at_utc',
        };

        // started_at_utc breaks ties so paging stays stable across requests.
        return $column . ' ' . $direction . ', c.started_at_utc DESC';
    }

    /**
     * @param array<string, mixed>              $params
     * @param array<string, ArrayParameterType> $types
     */
    private function practitionerOrderExpression(
        SearchConsultationsCriteria $criteria,
        array &$params,
        array &$types,
    ): string {
        if ([] === $criteria->practitionerOrder) {
            return 'c.practitioner_user_id';
        }

        $params['practitionerOrder'] = self::toBinaryIds($criteria->practitionerOrder);
        $types['practitionerOrder']  = ArrayParameterType::BINARY;

        return 'FIELD(c.practitioner_user_id, :practitionerOrder)';
    }

    /**
     * @param list<string> $binaryConsultationIds
     *
     * @return array<string, list<string>>
     */
    private function fetchMotifsFor(array $binaryConsultationIds): array
    {
        if ([] === $binaryConsultationIds) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT consultation_id, label FROM consultation__motifs
             WHERE consultation_id IN (:ids) ORDER BY position ASC',
            ['ids' => $binaryConsultationIds],
            ['ids' => ArrayParameterType::BINARY],
        );

        $motifs = [];

        foreach ($rows as $row) {
            $motifs[RowAccessor::string($row, 'consultation_id')][] = RowAccessor::string($row, 'label');
        }

        return $motifs;
    }

    /**
     * Pre-tax total per tax category for each consultation, in one query.
     *
     * @param list<string> $binaryConsultationIds
     *
     * @return array<string, array{htByTaxCategory: array<string, int>, currency: string}>
     */
    private function fetchAmountsFor(array $binaryConsultationIds): array
    {
        if ([] === $binaryConsultationIds) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            "SELECT consultation_id, COALESCE(tax_category_code, '') AS tax_category_code,
                    currency, SUM(quantity * unit_price_minor_units) AS total_ht
             FROM consultation__billing_lines
             WHERE consultation_id IN (:ids)
             GROUP BY consultation_id, tax_category_code, currency",
            ['ids' => $binaryConsultationIds],
            ['ids' => ArrayParameterType::BINARY],
        );

        $amounts = [];

        foreach ($rows as $row) {
            $consultationId = RowAccessor::string($row, 'consultation_id');
            $category       = RowAccessor::string($row, 'tax_category_code');

            $amounts[$consultationId]['htByTaxCategory'][$category] = (int) round(
                (float) RowAccessor::string($row, 'total_ht')
            );
            $amounts[$consultationId]['currency'] = RowAccessor::string($row, 'currency');
        }

        return $amounts;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private static function toBinaryIds(array $ids): array
    {
        return array_map(
            static fn (string $id): string => Uuid::fromString($id)->toBinary(),
            $ids,
        );
    }

    /**
     * @return list<array{noteType: string, content: string, createdAt: string}>
     */
    private function fetchNotes(string $consultationIdBinary): array
    {
        $rows = $this->fetchChildren(
            'SELECT note_type, content, created_at_utc
             FROM consultation__clinical_notes
             WHERE consultation_id = :consultationId
             ORDER BY created_at_utc ASC',
            $consultationIdBinary,
        );

        return array_map(
            /**
             * @param array<string, mixed> $row
             *
             * @return array{noteType: string, content: string, createdAt: string}
             */
            static fn (array $row): array => [
                'noteType'  => RowAccessor::string($row, 'note_type'),
                'content'   => RowAccessor::string($row, 'content'),
                'createdAt' => RowAccessor::string($row, 'created_at_utc'),
            ],
            $rows,
        );
    }

    /**
     * @return list<array{label: string, quantity: string, performedAt: string}>
     */
    private function fetchActs(string $consultationIdBinary): array
    {
        $rows = $this->fetchChildren(
            'SELECT label, quantity, performed_at_utc
             FROM consultation__performed_acts
             WHERE consultation_id = :consultationId
             ORDER BY performed_at_utc ASC',
            $consultationIdBinary,
        );

        return array_map(
            /**
             * @param array<string, mixed> $row
             *
             * @return array{label: string, quantity: string, performedAt: string}
             */
            static fn (array $row): array => [
                'label'       => RowAccessor::string($row, 'label'),
                'quantity'    => RowAccessor::string($row, 'quantity'),
                'performedAt' => RowAccessor::string($row, 'performed_at_utc'),
            ],
            $rows,
        );
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function fetchMotifs(string $consultationIdBinary): array
    {
        $rows = $this->fetchChildren(
            'SELECT id, label FROM consultation__motifs
             WHERE consultation_id = :consultationId ORDER BY position ASC',
            $consultationIdBinary,
        );

        return array_map(
            /**
             * @param array<string, mixed> $row
             *
             * @return array{id: string, label: string}
             */
            static fn (array $row): array => [
                'id'    => RowAccessor::uuid($row, 'id'),
                'label' => RowAccessor::string($row, 'label'),
            ],
            $rows,
        );
    }

    /**
     * @return list<array{id: string, type: string, value: string, recordedAt: string}>
     */
    private function fetchTypedVitals(string $consultationIdBinary): array
    {
        $rows = $this->fetchChildren(
            'SELECT id, type, value, recorded_at_utc FROM consultation__typed_vitals
             WHERE consultation_id = :consultationId ORDER BY position ASC',
            $consultationIdBinary,
        );

        return array_map(
            /**
             * @param array<string, mixed> $row
             *
             * @return array{id: string, type: string, value: string, recordedAt: string}
             */
            static fn (array $row): array => [
                'id'         => RowAccessor::uuid($row, 'id'),
                'type'       => RowAccessor::string($row, 'type'),
                'value'      => RowAccessor::string($row, 'value'),
                'recordedAt' => RowAccessor::string($row, 'recorded_at_utc'),
            ],
            $rows,
        );
    }

    /**
     * @return list<array{
     *     id: string,
     *     system: string,
     *     status: string,
     *     notes: ?string,
     *     structuredData: array<string, string>
     * }>
     */
    private function fetchExamSystems(string $consultationIdBinary): array
    {
        $rows = $this->fetchChildren(
            'SELECT id, `system`, status, notes, structured_data FROM consultation__exam_systems
             WHERE consultation_id = :consultationId ORDER BY position ASC',
            $consultationIdBinary,
        );

        return array_map(
            /**
             * @param array<string, mixed> $row
             *
             * @return array{
             *     id: string,
             *     system: string,
             *     status: string,
             *     notes: ?string,
             *     structuredData: array<string, string>
             * }
             */
            fn (array $row): array => [
                'id'             => RowAccessor::uuid($row, 'id'),
                'system'         => RowAccessor::string($row, 'system'),
                'status'         => RowAccessor::string($row, 'status'),
                'notes'          => RowAccessor::nullableString($row, 'notes'),
                'structuredData' => $this->decodeStructuredData(
                    RowAccessor::string($row, 'structured_data'),
                ),
            ],
            $rows,
        );
    }

    /**
     * @return list<array{
     *     id: string,
     *     code: ?string,
     *     label: string,
     *     certainty: string,
     *     note: ?string,
     *     isPrimary: bool,
     *     source: string
     * }>
     */
    private function fetchDiagnoses(string $consultationIdBinary): array
    {
        $rows = $this->fetchChildren(
            'SELECT id, code, label, certainty, note, is_primary, source FROM consultation__diagnoses
             WHERE consultation_id = :consultationId ORDER BY position ASC',
            $consultationIdBinary,
        );

        return array_map(
            /**
             * @param array<string, mixed> $row
             *
             * @return array{
             *     id: string,
             *     code: ?string,
             *     label: string,
             *     certainty: string,
             *     note: ?string,
             *     isPrimary: bool,
             *     source: string
             * }
             */
            static fn (array $row): array => [
                'id'        => RowAccessor::uuid($row, 'id'),
                'code'      => RowAccessor::nullableString($row, 'code'),
                'label'     => RowAccessor::string($row, 'label'),
                'certainty' => RowAccessor::string($row, 'certainty'),
                'note'      => RowAccessor::nullableString($row, 'note'),
                'isPrimary' => 1 === RowAccessor::int($row, 'is_primary'),
                'source'    => RowAccessor::string($row, 'source'),
            ],
            $rows,
        );
    }

    /**
     * @return list<array{
     *     id: string,
     *     kind: string,
     *     description: string,
     *     catalogCode: ?string,
     *     posology: ?string,
     *     durationDays: ?int,
     *     followUpDays: ?int,
     *     quantity: float,
     *     unitPriceMinorUnits: ?int,
     *     currency: ?string
     * }>
     */
    private function fetchPlanActions(string $consultationIdBinary): array
    {
        $rows = $this->fetchChildren(
            'SELECT id, kind, description, catalog_code, posology, duration_days, follow_up_days,
                    quantity, unit_price_minor_units, currency
             FROM consultation__plan_actions
             WHERE consultation_id = :consultationId ORDER BY position ASC',
            $consultationIdBinary,
        );

        return array_map(
            /**
             * @param array<string, mixed> $row
             *
             * @return array{
             *     id: string,
             *     kind: string,
             *     description: string,
             *     catalogCode: ?string,
             *     posology: ?string,
             *     durationDays: ?int,
             *     followUpDays: ?int,
             *     quantity: float,
             *     unitPriceMinorUnits: ?int,
             *     currency: ?string
             * }
             */
            static fn (array $row): array => [
                'id'                  => RowAccessor::uuid($row, 'id'),
                'kind'                => RowAccessor::string($row, 'kind'),
                'description'         => RowAccessor::string($row, 'description'),
                'catalogCode'         => RowAccessor::nullableString($row, 'catalog_code'),
                'posology'            => RowAccessor::nullableString($row, 'posology'),
                'durationDays'        => RowAccessor::nullableInt($row, 'duration_days'),
                'followUpDays'        => RowAccessor::nullableInt($row, 'follow_up_days'),
                'quantity'            => (float) RowAccessor::string($row, 'quantity'),
                'unitPriceMinorUnits' => RowAccessor::nullableInt($row, 'unit_price_minor_units'),
                'currency'            => RowAccessor::nullableString($row, 'currency'),
            ],
            $rows,
        );
    }

    /**
     * @return list<array{
     *     id: string,
     *     articleId: ?string,
     *     code: ?string,
     *     label: string,
     *     dose: ?string,
     *     frequency: ?string,
     *     durationDays: ?int,
     *     route: ?string,
     *     quantity: float,
     *     unitPriceMinorUnits: int,
     *     currency: string
     * }>
     */
    private function fetchPrescriptionLines(string $consultationIdBinary): array
    {
        $rows = $this->fetchChildren(
            'SELECT id, article_id, code, label, dose, frequency, duration_days, route,
                    quantity, unit_price_minor_units, currency
             FROM consultation__prescription_lines
             WHERE consultation_id = :consultationId ORDER BY position ASC',
            $consultationIdBinary,
        );

        return array_map(
            /**
             * @param array<string, mixed> $row
             *
             * @return array{
             *     id: string,
             *     articleId: ?string,
             *     code: ?string,
             *     label: string,
             *     dose: ?string,
             *     frequency: ?string,
             *     durationDays: ?int,
             *     route: ?string,
             *     quantity: float,
             *     unitPriceMinorUnits: int,
             *     currency: string
             * }
             */
            static fn (array $row): array => [
                'id'                  => RowAccessor::uuid($row, 'id'),
                'articleId'           => RowAccessor::nullableUuid($row, 'article_id'),
                'code'                => RowAccessor::nullableString($row, 'code'),
                'label'               => RowAccessor::string($row, 'label'),
                'dose'                => RowAccessor::nullableString($row, 'dose'),
                'frequency'           => RowAccessor::nullableString($row, 'frequency'),
                'durationDays'        => RowAccessor::nullableInt($row, 'duration_days'),
                'route'               => RowAccessor::nullableString($row, 'route'),
                'quantity'            => (float) RowAccessor::string($row, 'quantity'),
                'unitPriceMinorUnits' => RowAccessor::int($row, 'unit_price_minor_units'),
                'currency'            => RowAccessor::string($row, 'currency'),
            ],
            $rows,
        );
    }

    /**
     * @return list<array{
     *     id: string,
     *     sourceLineId: string,
     *     source: string,
     *     label: string,
     *     code: ?string,
     *     quantity: float,
     *     unitPriceMinorUnits: int,
     *     currency: string,
     *     taxCategoryCode: ?string,
     *     totalMinorUnits: int
     * }>
     */
    private function fetchBillingLines(string $consultationIdBinary): array
    {
        $rows = $this->fetchChildren(
            'SELECT id, source_line_id, source, label, code, quantity,
                    unit_price_minor_units, currency, tax_category_code
             FROM consultation__billing_lines
             WHERE consultation_id = :consultationId ORDER BY position ASC',
            $consultationIdBinary,
        );

        return array_map(
            /**
             * @param array<string, mixed> $row
             *
             * @return array{
             *     id: string,
             *     sourceLineId: string,
             *     source: string,
             *     label: string,
             *     code: ?string,
             *     quantity: float,
             *     unitPriceMinorUnits: int,
             *     currency: string,
             *     taxCategoryCode: ?string,
             *     totalMinorUnits: int
             * }
             */
            static function (array $row): array {
                $quantity  = (float) RowAccessor::string($row, 'quantity');
                $unitPrice = RowAccessor::int($row, 'unit_price_minor_units');

                return [
                    'id'                  => RowAccessor::uuid($row, 'id'),
                    'sourceLineId'        => RowAccessor::uuid($row, 'source_line_id'),
                    'source'              => RowAccessor::string($row, 'source'),
                    'label'               => RowAccessor::string($row, 'label'),
                    'code'                => RowAccessor::nullableString($row, 'code'),
                    'quantity'            => $quantity,
                    'unitPriceMinorUnits' => $unitPrice,
                    'currency'            => RowAccessor::string($row, 'currency'),
                    'taxCategoryCode'     => RowAccessor::nullableString($row, 'tax_category_code'),
                    'totalMinorUnits'     => (int) round($unitPrice * $quantity),
                ];
            },
            $rows,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchChildren(string $sql, string $consultationIdBinary): array
    {
        return $this->connection->fetchAllAssociative($sql, ['consultationId' => $consultationIdBinary]);
    }

    /**
     * The column is `JSON NOT NULL`, so anything other than a map of strings is
     * data written outside this repository — it degrades to an empty drill-down
     * rather than breaking the whole cockpit payload.
     *
     * @return array<string, string>
     */
    private function decodeStructuredData(string $json): array
    {
        $decoded = json_decode($json, true);

        if (!\is_array($decoded)) {
            return [];
        }

        $structured = [];

        foreach ($decoded as $key => $value) {
            if (\is_string($key) && \is_string($value)) {
                $structured[$key] = $value;
            }
        }

        return $structured;
    }
}
