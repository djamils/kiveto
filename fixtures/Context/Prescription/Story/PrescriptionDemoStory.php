<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Prescription\Story;

use App\Context\Prescription\Application\Command\AllowPrescriptionRenewals\AllowPrescriptionRenewals;
use App\Context\Prescription\Application\Command\CancelPrescription\CancelPrescription;
use App\Context\Prescription\Application\Command\IssuePrescription\IssuePrescription;
use App\Context\Prescription\Application\Command\PreparePrescriptionForConsultation\PreparePrescriptionForConsultation;
use App\Context\Prescription\Application\Command\RenewPrescription\RenewPrescription;
use App\Context\Prescription\Application\Command\SetPrescriptionNotes\SetPrescriptionNotes;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Demo ordonnances for both clinics.
 *
 * Only consultations that actually prescribed something get one — a
 * prescription without a medicine is not a document. Everything goes through
 * the command bus, so the demo data is built by the same invariants as
 * production writes.
 */
final class PrescriptionDemoStory extends Story
{
    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string LYON  = ClinicDataStory::GROUP_CLINIC_ID;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly Connection $connection,
    ) {
    }

    public function build(): void
    {
        foreach ([self::PARIS, self::LYON] as $clinicId) {
            $consultations = $this->consultationsWithMedicines($clinicId);

            foreach ($consultations as $index => $consultation) {
                // The oldest is cancelled and rewritten, the next one stands:
                // between the two clinics the list shows every state, and the
                // live one is the interesting one to look at.
                match ($index) {
                    0 => 1 < \count($consultations)
                        ? $this->cancelledOrdonnance($clinicId, $consultation)
                        : $this->renewableOrdonnance($clinicId, $consultation),
                    1       => $this->renewableOrdonnance($clinicId, $consultation),
                    default => null,
                };
            }
        }
    }

    /**
     * @param array{consultationId: string, practitionerUserId: string} $consultation
     */
    private function renewableOrdonnance(string $clinicId, array $consultation): void
    {
        $prescriptionId = $this->prepare($clinicId, $consultation);

        if (null === $prescriptionId) {
            return;
        }

        // A controlled substance refuses renewals, so this only lands on the
        // ordinary ones.
        if (!$this->carriesControlledSubstance($prescriptionId)) {
            $this->commandBus->dispatch(new AllowPrescriptionRenewals($clinicId, $prescriptionId, 2));
        }

        $this->commandBus->dispatch(new SetPrescriptionNotes(
            clinicId: $clinicId,
            prescriptionId: $prescriptionId,
            notes: "Administrer au cours du repas.\nEn cas de vomissements, interrompre et rappeler la clinique.",
        ));

        $this->commandBus->dispatch(new IssuePrescription($clinicId, $prescriptionId));

        if (!$this->carriesControlledSubstance($prescriptionId)) {
            $this->commandBus->dispatch(new RenewPrescription($clinicId, $prescriptionId));
        }
    }

    /**
     * @param array{consultationId: string, practitionerUserId: string} $consultation
     */
    private function cancelledOrdonnance(string $clinicId, array $consultation): void
    {
        $prescriptionId = $this->prepare($clinicId, $consultation);

        if (null === $prescriptionId) {
            return;
        }

        $this->commandBus->dispatch(new IssuePrescription($clinicId, $prescriptionId));
        $this->commandBus->dispatch(new CancelPrescription(
            clinicId: $clinicId,
            prescriptionId: $prescriptionId,
            reason: 'Erreur de posologie, réécrite',
        ));
    }

    /**
     * @param array{consultationId: string, practitionerUserId: string} $consultation
     */
    private function prepare(string $clinicId, array $consultation): ?string
    {
        $prescriptionId = $this->commandBus->dispatch(new PreparePrescriptionForConsultation(
            clinicId: $clinicId,
            consultationId: $consultation['consultationId'],
            prescriberUserId: $consultation['practitionerUserId'],
        ));

        return \is_string($prescriptionId) ? $prescriptionId : null;
    }

    private function carriesControlledSubstance(string $prescriptionId): bool
    {
        $count = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM prescription__prescription_lines
             WHERE prescription_id = :id AND prescription_class IN ('RX_CONTROLLED', 'RX_NARCOTIC')",
            ['id' => Uuid::fromString($prescriptionId)->toBinary()],
        );

        return is_numeric($count) && (int) $count > 0;
    }

    /**
     * Consultations that actually prescribed something, oldest first.
     *
     * @return list<array{consultationId: string, practitionerUserId: string}>
     */
    private function consultationsWithMedicines(string $clinicId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT BIN_TO_UUID(c.id) AS consultation_id,
                    BIN_TO_UUID(c.practitioner_user_id) AS practitioner_user_id
             FROM consultation__consultations c
             WHERE c.clinic_id = :clinicId
               AND EXISTS (
                   SELECT 1 FROM consultation__prescription_lines pl WHERE pl.consultation_id = c.id
               )
             ORDER BY c.started_at_utc ASC',
            ['clinicId' => Uuid::fromString($clinicId)->toBinary()],
        );

        $consultations = [];

        foreach ($rows as $row) {
            $consultations[] = [
                'consultationId'     => RowAccessor::string($row, 'consultation_id'),
                'practitionerUserId' => RowAccessor::string($row, 'practitioner_user_id'),
            ];
        }

        return $consultations;
    }
}
