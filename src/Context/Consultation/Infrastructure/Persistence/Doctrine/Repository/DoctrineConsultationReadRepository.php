<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\ListConsultationRegister\ConsultationRegisterCriteria;
use App\Context\Consultation\Application\Query\ListConsultationRegister\ConsultationRegisterRow;
use App\Context\Consultation\Application\Query\SearchConsultations\ConsultationListRow;
use App\Context\Consultation\Application\Query\SearchConsultations\SearchConsultationsCriteria;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
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

    public function hasOpenConsultationForAdmission(
        AdmissionId $admissionId,
        ClinicId $clinicId,
        ConsultationId $excluding,
    ): bool {
        $count = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM consultation__consultations
             WHERE clinic_id = :clinicId AND admission_id = :admissionId
               AND id <> :excluding AND status = 'OPEN'",
            [
                'clinicId'    => Uuid::fromString($clinicId->toString())->toBinary(),
                'admissionId' => Uuid::fromString($admissionId->toString())->toBinary(),
                'excluding'   => Uuid::fromString($excluding->toString())->toBinary(),
            ],
        );

        return is_numeric($count) && (int) $count > 0;
    }

    public function totalsForAdmissions(ClinicId $clinicId, array $admissionIds): array
    {
        if ([] === $admissionIds) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(c.admission_id) AS admission_id,
                    COALESCE(bl.tax_category_code, '') AS tax_category_code,
                    bl.currency, bl.unit_price_mode, SUM(bl.quantity * bl.unit_price_minor_units) AS total
             FROM consultation__consultations c
             INNER JOIN consultation__billing_lines bl ON bl.consultation_id = c.id
             WHERE c.clinic_id = :clinicId AND c.admission_id IN (:admissionIds)
             GROUP BY c.admission_id, bl.tax_category_code, bl.currency, bl.unit_price_mode",
            [
                'clinicId'     => Uuid::fromString($clinicId->toString())->toBinary(),
                'admissionIds' => self::toBinaryIds($admissionIds),
            ],
            ['admissionIds' => ArrayParameterType::BINARY],
        );

        $totals = [];

        foreach ($rows as $row) {
            $admissionId = RowAccessor::string($row, 'admission_id');
            $totals[$admissionId] ??= ['htByTaxCategory' => [], 'ttcByTaxCategory' => [], 'currency' => ''];

            $totals[$admissionId][self::amountsKey($row)][RowAccessor::string($row, 'tax_category_code')] = self::sum($row);
            $totals[$admissionId]['currency']                                                             = RowAccessor::string($row, 'currency');
        }

        return $totals;
    }

    public function billingLinesForAdmission(ClinicId $clinicId, AdmissionId $admissionId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(c.id) AS consultation_id, BIN_TO_UUID(bl.id) AS source_line_id,
                    bl.label, bl.code, bl.quantity, bl.unit_price_minor_units, bl.currency,
                    bl.unit_price_mode, bl.tax_category_code
             FROM consultation__consultations c
             INNER JOIN consultation__billing_lines bl ON bl.consultation_id = c.id
             WHERE c.clinic_id = :clinicId AND c.admission_id = :admissionId
             ORDER BY c.started_at_utc ASC, bl.position ASC',
            [
                'clinicId'    => Uuid::fromString($clinicId->toString())->toBinary(),
                'admissionId' => Uuid::fromString($admissionId->toString())->toBinary(),
            ],
        );

        $lines = [];

        foreach ($rows as $row) {
            $lines[] = [
                'consultationId'      => RowAccessor::string($row, 'consultation_id'),
                'sourceLineId'        => RowAccessor::string($row, 'source_line_id'),
                'label'               => RowAccessor::string($row, 'label'),
                'code'                => RowAccessor::nullableString($row, 'code'),
                'quantity'            => RowAccessor::string($row, 'quantity'),
                'unitPriceMinorUnits' => RowAccessor::int($row, 'unit_price_minor_units'),
                'currency'            => RowAccessor::string($row, 'currency'),
                'unitPriceMode'       => RowAccessor::string($row, 'unit_price_mode'),
                'taxCategoryCode'     => RowAccessor::nullableString($row, 'tax_category_code'),
            ];
        }

        return $lines;
    }

    public function prescriptionLinesForConsultation(ClinicId $clinicId, ConsultationId $consultationId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(pl.id) AS source_line_id, BIN_TO_UUID(pl.article_id) AS article_id,
                    pl.code, pl.label, pl.dose, pl.frequency, pl.duration_days, pl.route, pl.quantity
             FROM consultation__prescription_lines pl
             INNER JOIN consultation__consultations c ON c.id = pl.consultation_id
             WHERE c.id = :consultationId AND c.clinic_id = :clinicId
             ORDER BY pl.position ASC',
            [
                'consultationId' => Uuid::fromString($consultationId->toString())->toBinary(),
                'clinicId'       => Uuid::fromString($clinicId->toString())->toBinary(),
            ],
        );

        $lines = [];

        foreach ($rows as $row) {
            $lines[] = [
                'sourceLineId' => RowAccessor::string($row, 'source_line_id'),
                'articleId'    => RowAccessor::nullableString($row, 'article_id'),
                'code'         => RowAccessor::nullableString($row, 'code'),
                'label'        => RowAccessor::string($row, 'label'),
                'dose'         => RowAccessor::nullableString($row, 'dose'),
                'frequency'    => RowAccessor::nullableString($row, 'frequency'),
                'durationDays' => RowAccessor::nullableInt($row, 'duration_days'),
                'route'        => RowAccessor::nullableString($row, 'route'),
                'quantity'     => RowAccessor::string($row, 'quantity'),
            ];
        }

        return $lines;
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
            $amount   = $amounts[$binaryId] ?? ['htByTaxCategory' => [], 'ttcByTaxCategory' => [], 'currency' => ''];

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
                ttcByTaxCategory: $amount['ttcByTaxCategory'],
                currency: $amount['currency'],
            );
        }

        return ['items' => $items, 'total' => $total];
    }

    public function register(ClinicId $clinicId, ConsultationRegisterCriteria $criteria): array
    {
        if ($criteria->isImpossible()) {
            return ['items' => [], 'total' => 0];
        }

        $filter = $this->buildRegisterFilter($clinicId, $criteria);

        $totalRaw = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM consultation__consultations c WHERE ' . $filter['where'],
            $filter['params'],
            $filter['types'],
        );
        $total = is_numeric($totalRaw) ? (int) $totalRaw : 0;

        if (0 === $total) {
            return ['items' => [], 'total' => 0];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT c.id, c.admission_id, c.patient_id, c.practitioner_user_id, c.status,
                    c.started_at_utc, c.closed_at_utc, c.chief_complaint, c.summary, c.weight_kg
             FROM consultation__consultations c
             WHERE ' . $filter['where'] . '
             ORDER BY c.started_at_utc DESC, c.id DESC
             LIMIT ' . $criteria->limit . ' OFFSET ' . $criteria->offset(),
            $filter['params'],
            $filter['types'],
        );

        $binaryIds = array_map(
            /** @param array<string, mixed> $row */
            static fn (array $row): string => RowAccessor::string($row, 'id'),
            $rows,
        );

        $motifs    = $this->fetchMotifsFor($binaryIds);
        $amounts   = $this->fetchAmountsFor($binaryIds);
        $diagnoses = $this->fetchPrimaryDiagnosesFor($binaryIds);
        $billed    = $this->fetchBilledItemsFor($binaryIds);

        $items = [];

        foreach ($rows as $row) {
            $binaryId = RowAccessor::string($row, 'id');
            $amount   = $amounts[$binaryId] ?? ['htByTaxCategory' => [], 'ttcByTaxCategory' => [], 'currency' => ''];

            $items[] = new ConsultationRegisterRow(
                consultationId: RowAccessor::uuid($row, 'id'),
                admissionId: RowAccessor::uuid($row, 'admission_id'),
                patientId: RowAccessor::uuid($row, 'patient_id'),
                practitionerUserId: RowAccessor::uuid($row, 'practitioner_user_id'),
                status: RowAccessor::string($row, 'status'),
                startedAtUtc: RowAccessor::string($row, 'started_at_utc'),
                closedAtUtc: RowAccessor::nullableString($row, 'closed_at_utc'),
                chiefComplaint: RowAccessor::nullableString($row, 'chief_complaint'),
                summary: RowAccessor::nullableString($row, 'summary'),
                motifs: $motifs[$binaryId] ?? [],
                primaryDiagnosis: $diagnoses[$binaryId] ?? null,
                weightKg: RowAccessor::nullableString($row, 'weight_kg'),
                billedItems: $billed[$binaryId] ?? [],
                htByTaxCategory: $amount['htByTaxCategory'],
                ttcByTaxCategory: $amount['ttcByTaxCategory'],
                currency: $amount['currency'],
            );
        }

        return ['items' => $items, 'total' => $total];
    }

    public function registerCounters(ClinicId $clinicId, \DateTimeImmutable $dayStartUtc, array $departedAdmissionIds): array
    {
        $params = [
            'clinicId' => Uuid::fromString($clinicId->toString())->toBinary(),
            'dayStart' => $dayStartUtc->format('Y-m-d H:i:s'),
        ];
        $types    = [];
        $departed = '0';

        if ([] !== $departedAdmissionIds) {
            $departed           = 'c.admission_id IN (:departed)';
            $params['departed'] = self::toBinaryIds($departedAdmissionIds);
            $types['departed']  = ArrayParameterType::BINARY;
        }

        $row = $this->connection->fetchAssociative(
            "SELECT
                 COALESCE(SUM(c.status = 'OPEN' AND c.started_at_utc >= :dayStart AND NOT ({$departed})), 0) AS in_progress,
                 COALESCE(SUM(c.status = 'OPEN' AND (c.started_at_utc < :dayStart OR {$departed})), 0) AS to_close,
                 COALESCE(SUM(c.started_at_utc >= :dayStart), 0) AS today,
                 COUNT(*) AS recorded
             FROM consultation__consultations c
             WHERE c.clinic_id = :clinicId",
            $params,
            $types,
        );
        \assert(\is_array($row));

        return [
            'inProgress' => (int) RowAccessor::string($row, 'in_progress'),
            'toClose'    => (int) RowAccessor::string($row, 'to_close'),
            'today'      => (int) RowAccessor::string($row, 'today'),
            'recorded'   => (int) RowAccessor::string($row, 'recorded'),
        ];
    }

    public function openConsultationAdmissionIds(ClinicId $clinicId): array
    {
        $ids = $this->connection->fetchFirstColumn(
            "SELECT DISTINCT BIN_TO_UUID(admission_id) FROM consultation__consultations
             WHERE clinic_id = :clinicId AND status = 'OPEN'",
            ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()],
        );

        return array_values(array_filter($ids, 'is_string'));
    }

    /**
     * @return array{
     *     where: string,
     *     params: array<string, mixed>,
     *     types: array<string, ArrayParameterType>
     * }
     */
    private function buildRegisterFilter(ClinicId $clinicId, ConsultationRegisterCriteria $criteria): array
    {
        $where  = ['c.clinic_id = :clinicId'];
        $params = ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()];
        /** @var array<string, ArrayParameterType> $types */
        $types = [];

        if (null !== $criteria->startedAfterUtc) {
            $where[]                = 'c.started_at_utc >= :startedAfter';
            $params['startedAfter'] = $criteria->startedAfterUtc->format('Y-m-d H:i:s');
        }

        if (ConsultationRegisterCriteria::STAGE_CLOSED === $criteria->stage) {
            $where[] = "c.status = 'CLOSED'";
        } elseif (null !== $criteria->stage) {
            $params['dayStart'] = $criteria->dayStartUtc->format('Y-m-d H:i:s');
            $departed           = '0';

            if ([] !== $criteria->departedAdmissionIds) {
                $departed           = 'c.admission_id IN (:departed)';
                $params['departed'] = self::toBinaryIds($criteria->departedAdmissionIds);
                $types['departed']  = ArrayParameterType::BINARY;
            }

            $where[] = ConsultationRegisterCriteria::STAGE_TO_CLOSE === $criteria->stage
                ? "c.status = 'OPEN' AND (c.started_at_utc < :dayStart OR {$departed})"
                : "c.status = 'OPEN' AND c.started_at_utc >= :dayStart AND NOT ({$departed})";
        }

        if (null !== $criteria->practitionerUserId) {
            $where[]                  = 'c.practitioner_user_id = :practitionerId';
            $params['practitionerId'] = Uuid::fromString($criteria->practitionerUserId)->toBinary();
        }

        if (null !== $criteria->restrictToAdmissionIds) {
            $where[]                = 'c.admission_id IN (:admissionIds)';
            $params['admissionIds'] = self::toBinaryIds($criteria->restrictToAdmissionIds);
            $types['admissionIds']  = ArrayParameterType::BINARY;
        }

        if (null !== $criteria->restrictToActCodes) {
            $where[]            = 'EXISTS (SELECT 1 FROM consultation__billing_lines b WHERE b.consultation_id = c.id AND b.code IN (:actCodes))';
            $params['actCodes'] = $criteria->restrictToActCodes;
            $types['actCodes']  = ArrayParameterType::STRING;
        }

        if (null !== $criteria->consultationIds) {
            $where[]                   = 'c.id IN (:consultationIds)';
            $params['consultationIds'] = self::toBinaryIds($criteria->consultationIds);
            $types['consultationIds']  = ArrayParameterType::BINARY;
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
            $params['term'] = '%' . addcslashes($term, '%_\\') . '%';

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
     * The diagnosis marked primary of each consultation, else its first one.
     *
     * @param list<string> $binaryConsultationIds
     *
     * @return array<string, string>
     */
    private function fetchPrimaryDiagnosesFor(array $binaryConsultationIds): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT consultation_id, label FROM consultation__diagnoses
             WHERE consultation_id IN (:ids) ORDER BY is_primary DESC, position ASC',
            ['ids' => $binaryConsultationIds],
            ['ids' => ArrayParameterType::BINARY],
        );

        $diagnoses = [];

        foreach ($rows as $row) {
            $diagnoses[RowAccessor::string($row, 'consultation_id')] ??= RowAccessor::string($row, 'label');
        }

        return $diagnoses;
    }

    /**
     * Code and label of every billing line of each consultation, in order.
     *
     * @param list<string> $binaryConsultationIds
     *
     * @return array<string, list<array{code: ?string, label: string}>>
     */
    private function fetchBilledItemsFor(array $binaryConsultationIds): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT consultation_id, code, label FROM consultation__billing_lines
             WHERE consultation_id IN (:ids) ORDER BY position ASC',
            ['ids' => $binaryConsultationIds],
            ['ids' => ArrayParameterType::BINARY],
        );

        $billed = [];

        foreach ($rows as $row) {
            $billed[RowAccessor::string($row, 'consultation_id')][] = [
                'code'  => RowAccessor::nullableString($row, 'code'),
                'label' => RowAccessor::string($row, 'label'),
            ];
        }

        return $billed;
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
     * @return array<string, array{htByTaxCategory: array<string, int>, ttcByTaxCategory: array<string, int>, currency: string}>
     */
    private function fetchAmountsFor(array $binaryConsultationIds): array
    {
        if ([] === $binaryConsultationIds) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            "SELECT consultation_id, COALESCE(tax_category_code, '') AS tax_category_code,
                    currency, unit_price_mode, SUM(quantity * unit_price_minor_units) AS total
             FROM consultation__billing_lines
             WHERE consultation_id IN (:ids)
             GROUP BY consultation_id, tax_category_code, currency, unit_price_mode",
            ['ids' => $binaryConsultationIds],
            ['ids' => ArrayParameterType::BINARY],
        );

        $amounts = [];

        foreach ($rows as $row) {
            $consultationId = RowAccessor::string($row, 'consultation_id');
            $amounts[$consultationId] ??= ['htByTaxCategory' => [], 'ttcByTaxCategory' => [], 'currency' => ''];

            $amounts[$consultationId][self::amountsKey($row)][RowAccessor::string($row, 'tax_category_code')] = self::sum($row);
            $amounts[$consultationId]['currency']                                                             = RowAccessor::string($row, 'currency');
        }

        return $amounts;
    }

    /**
     * Where a grouped sum of line prices belongs: with the pre-tax sums, or
     * with the all-taxes-included ones when the prices were entered TTC.
     *
     * @param array<string, mixed> $row
     *
     * @return 'htByTaxCategory'|'ttcByTaxCategory'
     */
    private static function amountsKey(array $row): string
    {
        return 'incl' === RowAccessor::string($row, 'unit_price_mode') ? 'ttcByTaxCategory' : 'htByTaxCategory';
    }

    /**
     * A grouped sum of quantity × unit price (a DECIMAL string), rounded to
     * the minor unit.
     *
     * @param array<string, mixed> $row
     */
    private static function sum(array $row): int
    {
        return (int) round((float) RowAccessor::string($row, 'total'));
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
     * @return list<array{id: string, noteType: string, content: string, createdAt: string, createdByUserId: string}>
     */
    private function fetchNotes(string $consultationIdBinary): array
    {
        $rows = $this->fetchChildren(
            'SELECT BIN_TO_UUID(id) AS id, note_type, content, created_at_utc, BIN_TO_UUID(created_by_user_id) AS created_by_user_id
             FROM consultation__clinical_notes
             WHERE consultation_id = :consultationId
             ORDER BY created_at_utc ASC',
            $consultationIdBinary,
        );

        return array_map(
            /**
             * @param array<string, mixed> $row
             *
             * @return array{id: string, noteType: string, content: string, createdAt: string, createdByUserId: string}
             */
            static fn (array $row): array => [
                'id'              => RowAccessor::string($row, 'id'),
                'noteType'        => RowAccessor::string($row, 'note_type'),
                'content'         => RowAccessor::string($row, 'content'),
                'createdAt'       => RowAccessor::string($row, 'created_at_utc'),
                'createdByUserId' => RowAccessor::string($row, 'created_by_user_id'),
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
     *     currency: ?string,
     *     unitPriceMode: ?string
     * }>
     */
    private function fetchPlanActions(string $consultationIdBinary): array
    {
        $rows = $this->fetchChildren(
            'SELECT id, kind, description, catalog_code, posology, duration_days, follow_up_days,
                    quantity, unit_price_minor_units, currency, unit_price_mode
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
             *     currency: ?string,
             *     unitPriceMode: ?string
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
                'unitPriceMode'       => RowAccessor::nullableString($row, 'unit_price_mode'),
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
     *     currency: string,
     *     unitPriceMode: string
     * }>
     */
    private function fetchPrescriptionLines(string $consultationIdBinary): array
    {
        $rows = $this->fetchChildren(
            'SELECT id, article_id, code, label, dose, frequency, duration_days, route,
                    quantity, unit_price_minor_units, currency, unit_price_mode
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
             *     currency: string,
             *     unitPriceMode: string
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
                'unitPriceMode'       => RowAccessor::string($row, 'unit_price_mode'),
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
     *     unitPriceMode: string,
     *     taxCategoryCode: ?string,
     *     totalMinorUnits: int
     * }>
     */
    private function fetchBillingLines(string $consultationIdBinary): array
    {
        $rows = $this->fetchChildren(
            'SELECT id, source_line_id, source, label, code, quantity,
                    unit_price_minor_units, currency, unit_price_mode, tax_category_code
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
             *     unitPriceMode: string,
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
                    'unitPriceMode'       => RowAccessor::string($row, 'unit_price_mode'),
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
