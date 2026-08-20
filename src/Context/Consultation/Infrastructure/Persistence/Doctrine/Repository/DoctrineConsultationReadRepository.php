<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
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
