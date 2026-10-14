<?php

declare(strict_types=1);

namespace App\Context\Prescription\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Prescription\Application\Port\PrescriptionReadRepositoryInterface;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionDetails;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\PrescriptionLineView;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\RenewalView;
use App\Context\Prescription\Application\Query\ListPrescriptionsForConsultation\ConsultationPrescriptionRow;
use App\Context\Prescription\Application\Query\SearchPrescriptions\PrescriptionListLine;
use App\Context\Prescription\Application\Query\SearchPrescriptions\PrescriptionListRow;
use App\Context\Prescription\Application\Query\SearchPrescriptions\SearchPrescriptionsCriteria;
use App\Context\Prescription\Domain\Repository\PrescriptionRepositoryInterface;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\ConsultationId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Read side of the Prescription BC.
 *
 * Expiry is decided against the clock at read time rather than stored: a
 * prescription does not change on its own, it simply stops being usable.
 */
final readonly class DbalPrescriptionReadRepository implements PrescriptionReadRepositoryInterface
{
    /**
     * Columns the list can be sorted on, mapped to their SQL expression.
     */
    private const array SORTS = [
        SearchPrescriptionsCriteria::SORT_DATE    => 'COALESCE(p.issued_at, p.created_at)',
        SearchPrescriptionsCriteria::SORT_NUMBER  => 'p.number',
        SearchPrescriptionsCriteria::SORT_PATIENT => 'p.patient_name',
        SearchPrescriptionsCriteria::SORT_STATUS  => 'p.status',
    ];

    public function __construct(
        private Connection $connection,
        private PrescriptionRepositoryInterface $prescriptions,
        private ClockInterface $clock,
    ) {
    }

    public function details(ClinicId $clinicId, PrescriptionId $prescriptionId): PrescriptionDetails
    {
        $prescription = $this->prescriptions->get($clinicId, $prescriptionId);
        $now          = $this->clock->now();
        $validity     = $prescription->validity();

        $lines = array_map(
            static fn ($line): PrescriptionLineView => new PrescriptionLineView(
                id: $line->id()->toString(),
                code: $line->code(),
                label: $line->label(),
                pharmaceuticalForm: $line->pharmaceuticalForm(),
                activeSubstances: $line->activeSubstances(),
                posology: $line->posology(),
                dose: $line->dose(),
                frequency: $line->frequency(),
                durationDays: $line->durationDays(),
                route: $line->route(),
                quantity: $line->quantity(),
                prescriptionClass: $line->prescriptionClass()->value,
                prescriptionClassLabel: $line->prescriptionClass()->label(),
                isControlled: $line->isControlled(),
                instructions: $line->instructions(),
                articleId: $line->articleId(),
            ),
            $prescription->lines(),
        );

        $renewals = array_map(
            static fn ($renewal): RenewalView => new RenewalView(
                id: $renewal->id()->toString(),
                renewedAt: $renewal->renewedAt(),
                newValidUntil: $renewal->newValidUntil(),
            ),
            $prescription->renewalHistory(),
        );

        return new PrescriptionDetails(
            id: $prescription->id()->toString(),
            clinicId: $prescription->clinicId()->toString(),
            consultationId: $prescription->consultationId()->toString(),
            number: $prescription->number()?->toString(),
            status: $prescription->status()->value,
            statusLabel: $prescription->status()->label(),
            isExpired: $prescription->isExpiredAt($now),
            isDispensable: $prescription->isDispensableAt($now),
            prescriberName: $prescription->prescriber()->printedName(),
            prescriberRegistrationNumber: $prescription->prescriber()->registrationNumber(),
            patientName: $prescription->patient()->name(),
            patientSpecies: $prescription->patient()->species(),
            patientBreed: $prescription->patient()->breed(),
            patientBirthDate: $prescription->patient()->birthDate(),
            patientMicrochipNumber: $prescription->patient()->microchipNumber(),
            ownerClientId: $prescription->owner()->clientId()?->toString(),
            ownerName: $prescription->owner()->displayName(),
            ownerAddressLines: $prescription->owner()->addressLines(),
            lines: $lines,
            renewals: $renewals,
            renewalsAllowed: $prescription->renewals()->allowed(),
            renewalsUsed: $prescription->renewals()->used(),
            renewalsRemaining: $prescription->renewals()->remaining(),
            carriesControlledSubstance: $prescription->carriesControlledSubstance(),
            validityDays: $prescription->validityDays(),
            issuedAt: $validity?->issuedAt(),
            validUntil: $validity?->validUntil(),
            notes: $prescription->notes(),
            createdAt: $prescription->createdAt(),
            cancelledAt: $prescription->cancelledAt(),
            cancellationReason: $prescription->cancellationReason(),
            prescriberId: $prescription->prescriber()->userId()->toString(),
            patientAnimalId: $prescription->patient()->animalId(),
            patientWeightKg: $prescription->patient()->weightKg(),
        );
    }

    public function search(ClinicId $clinicId, SearchPrescriptionsCriteria $criteria): array
    {
        if ($criteria->isImpossible()) {
            return ['items' => [], 'total' => 0];
        }

        $filter = $this->buildFilter($clinicId, $criteria);

        $totalRaw = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM prescription__prescriptions p WHERE ' . $filter['where'],
            $filter['params'],
            $filter['types'],
        );

        $total = is_numeric($totalRaw) ? (int) $totalRaw : 0;

        if (0 === $total) {
            return ['items' => [], 'total' => 0];
        }

        $sort      = self::SORTS[$criteria->sortBy] ?? self::SORTS[SearchPrescriptionsCriteria::SORT_DATE];
        $direction = $criteria->sortDescending ? 'DESC' : 'ASC';

        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT BIN_TO_UUID(p.id) AS id, p.number, p.status,
                        p.patient_name, p.patient_species, p.owner_name,
                        p.prescriber_display_name, p.prescriber_professional_title,
                        p.renewals_allowed, p.renewals_used,
                        p.created_at, p.issued_at, p.valid_until,
                        BIN_TO_UUID(p.consultation_id) AS consultation_id,
                        BIN_TO_UUID(p.prescriber_user_id) AS prescriber_id,
                        %s AS line_count,
                        %s AS controlled_count
                 FROM prescription__prescriptions p
                 WHERE %s
                 ORDER BY %s %s, p.created_at DESC
                 LIMIT %d OFFSET %d',
                $this->lineCountExpression(),
                $this->controlledCountExpression(),
                $filter['where'],
                $sort,
                $direction,
                // Both come from a whitelist in the controller, never from raw
                // input, and MySQL refuses a bound parameter here anyway.
                $criteria->perPage,
                $criteria->offset(),
            ),
            $filter['params'],
            $filter['types'],
        );

        $lines = $this->linesOf(array_map(
            static fn (array $row): string => RowAccessor::string($row, 'id'),
            $rows,
        ));
        $items = [];

        foreach ($rows as $row) {
            $id    = RowAccessor::string($row, 'id');
            $title = RowAccessor::nullableString($row, 'prescriber_professional_title');
            $name  = RowAccessor::string($row, 'prescriber_display_name');

            $items[] = new PrescriptionListRow(
                id: $id,
                number: RowAccessor::nullableString($row, 'number'),
                status: RowAccessor::string($row, 'status'),
                patientName: RowAccessor::string($row, 'patient_name'),
                patientSpecies: RowAccessor::nullableString($row, 'patient_species'),
                ownerName: RowAccessor::string($row, 'owner_name'),
                prescriberName: null !== $title
                    ? mb_convert_case(mb_strtolower($title), \MB_CASE_TITLE) . ' ' . $name
                    : $name,
                lineCount: (int) round((float) RowAccessor::string($row, 'line_count')),
                carriesControlledSubstance: 0 < (int) round((float) RowAccessor::string($row, 'controlled_count')),
                renewalsRemaining: RowAccessor::int($row, 'renewals_allowed')
                    - RowAccessor::int($row, 'renewals_used'),
                createdAt: new \DateTimeImmutable(RowAccessor::string($row, 'created_at')),
                issuedAt: null !== RowAccessor::nullableString($row, 'issued_at')
                    ? new \DateTimeImmutable(RowAccessor::string($row, 'issued_at'))
                    : null,
                validUntil: null !== RowAccessor::nullableString($row, 'valid_until')
                    ? new \DateTimeImmutable(RowAccessor::string($row, 'valid_until'))
                    : null,
                consultationId: RowAccessor::string($row, 'consultation_id'),
                prescriberId: RowAccessor::string($row, 'prescriber_id'),
                lines: $lines[$id] ?? [],
            );
        }

        return ['items' => $items, 'total' => $total];
    }

    public function forConsultation(ClinicId $clinicId, ConsultationId $consultationId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT BIN_TO_UUID(p.id) AS id, p.number, p.status, %s AS line_count
                 FROM prescription__prescriptions p
                 WHERE p.clinic_id = UUID_TO_BIN(:clinicId)
                   AND p.consultation_id = UUID_TO_BIN(:consultationId)
                 ORDER BY p.created_at ASC',
                $this->lineCountExpression(),
            ),
            [
                'clinicId'       => $clinicId->toString(),
                'consultationId' => $consultationId->toString(),
            ],
        );

        return array_map(
            static fn (array $row): ConsultationPrescriptionRow => new ConsultationPrescriptionRow(
                id: RowAccessor::string($row, 'id'),
                number: RowAccessor::nullableString($row, 'number'),
                status: RowAccessor::string($row, 'status'),
                lineCount: RowAccessor::int($row, 'line_count'),
            ),
            $rows,
        );
    }

    /**
     * @return array{
     *     where: string,
     *     params: array<string, mixed>,
     *     types: array<string, ArrayParameterType>
     * }
     */
    private function buildFilter(ClinicId $clinicId, SearchPrescriptionsCriteria $criteria): array
    {
        $where  = ['p.clinic_id = :clinicId'];
        $params = ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()];
        $types  = [];

        if (null !== $criteria->term && '' !== $criteria->term) {
            $where[] = '(p.patient_name LIKE :term OR p.owner_name LIKE :term OR p.number LIKE :term
                         OR EXISTS (SELECT 1 FROM prescription__prescription_lines tl
                                    WHERE tl.prescription_id = p.id AND tl.label LIKE :term))';
            $params['term'] = '%' . $criteria->term . '%';
        }

        if ([] !== $criteria->statuses) {
            $where[]            = 'p.status IN (:statuses)';
            $params['statuses'] = $criteria->statuses;
            $types['statuses']  = ArrayParameterType::STRING;
        }

        if (null !== $criteria->issuedFrom) {
            $where[]        = 'COALESCE(p.issued_at, p.created_at) >= :from';
            $params['from'] = $criteria->issuedFrom->format('Y-m-d H:i:s');
        }

        if (null !== $criteria->issuedTo) {
            $where[]      = 'COALESCE(p.issued_at, p.created_at) <= :to';
            $params['to'] = $criteria->issuedTo->format('Y-m-d H:i:s');
        }

        if (null !== $criteria->prescriberId) {
            $where[]                = 'p.prescriber_user_id = :prescriberId';
            $params['prescriberId'] = Uuid::fromString($criteria->prescriberId)->toBinary();
        }

        // Expiry is decided against the clock, as everywhere on the read side.
        if (SearchPrescriptionsCriteria::VALIDITY_CURRENT === $criteria->validity) {
            $where[]       = "p.status = 'ISSUED' AND p.valid_until >= :now";
            $params['now'] = $this->clock->now()->format('Y-m-d H:i:s');
        } elseif (SearchPrescriptionsCriteria::VALIDITY_EXPIRED === $criteria->validity) {
            $where[]       = "p.status = 'ISSUED' AND p.valid_until < :now";
            $params['now'] = $this->clock->now()->format('Y-m-d H:i:s');
        }

        if (true === $criteria->controlledOnly) {
            $where[] = $this->controlledCountExpression() . ' > 0';
        }

        return ['where' => implode(' AND ', $where), 'params' => $params, 'types' => $types];
    }

    /**
     * The medicines of one page of ordonnances, in one round trip.
     *
     * @param list<string> $ids
     *
     * @return array<string, list<PrescriptionListLine>> prescription id => its lines
     */
    private function linesOf(array $ids): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(l.prescription_id) AS prescription_id, l.label, l.prescription_class
             FROM prescription__prescription_lines l
             WHERE l.prescription_id IN (:ids)
             ORDER BY l.position ASC',
            ['ids' => array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $ids)],
            ['ids' => ArrayParameterType::BINARY],
        );

        $lines = [];

        foreach ($rows as $row) {
            $lines[RowAccessor::string($row, 'prescription_id')][] = new PrescriptionListLine(
                label: RowAccessor::string($row, 'label'),
                prescriptionClass: RowAccessor::string($row, 'prescription_class'),
            );
        }

        return $lines;
    }

    private function lineCountExpression(): string
    {
        return '(SELECT COUNT(*) FROM prescription__prescription_lines l WHERE l.prescription_id = p.id)';
    }

    private function controlledCountExpression(): string
    {
        return "(SELECT COUNT(*) FROM prescription__prescription_lines l
                 WHERE l.prescription_id = p.id
                   AND l.prescription_class IN ('RX_CONTROLLED', 'RX_NARCOTIC'))";
    }
}
