<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain;

use App\Context\Prescription\Domain\Event\PrescriptionCancelled;
use App\Context\Prescription\Domain\Event\PrescriptionDrafted;
use App\Context\Prescription\Domain\Event\PrescriptionIssued;
use App\Context\Prescription\Domain\Event\PrescriptionRenewed;
use App\Context\Prescription\Domain\Exception\PrescriberCannotSignException;
use App\Context\Prescription\Domain\Exception\PrescriptionCannotBeCancelledException;
use App\Context\Prescription\Domain\Exception\PrescriptionCannotBeRenewedException;
use App\Context\Prescription\Domain\Exception\PrescriptionHasNoLinesException;
use App\Context\Prescription\Domain\Exception\PrescriptionNotEditableException;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\ConsultationId;
use App\Context\Prescription\Domain\ValueObject\OwnerSnapshot;
use App\Context\Prescription\Domain\ValueObject\PatientSnapshot;
use App\Context\Prescription\Domain\ValueObject\Prescriber;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionLine;
use App\Context\Prescription\Domain\ValueObject\PrescriptionLineId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionNumber;
use App\Context\Prescription\Domain\ValueObject\PrescriptionStatus;
use App\Context\Prescription\Domain\ValueObject\Renewal;
use App\Context\Prescription\Domain\ValueObject\RenewalId;
use App\Context\Prescription\Domain\ValueObject\Renewals;
use App\Context\Prescription\Domain\ValueObject\Validity;
use App\Shared\Domain\Aggregate\AggregateRoot;

/**
 * The ordonnance: what a practitioner prescribed, for which animal, to whom.
 *
 * A draft is a working document — medicines come and go, the renewal allowance
 * can be set. Issuing signs it: it takes its number, its validity starts
 * running, and nothing on it moves again except how many times it has been
 * presented.
 *
 * Two rules shape the rest. A prescriber without a registration number cannot
 * sign, because that number is what makes the sheet a prescription. And one
 * controlled medicine pulls the whole document into the strict regime: 28 days
 * at most, no renewal.
 */
final class Prescription extends AggregateRoot
{
    /**
     * Default span of an ordinary prescription.
     */
    public const int DEFAULT_VALIDITY_DAYS = 365;

    /**
     * The longest a prescription carrying a controlled substance may run.
     */
    public const int CONTROLLED_VALIDITY_DAYS = 28;

    /**
     * @param list<PrescriptionLine> $lines
     * @param list<Renewal>          $renewalHistory
     */
    private function __construct(
        private readonly PrescriptionId $id,
        private readonly ClinicId $clinicId,
        private readonly ConsultationId $consultationId,
        private ?PrescriptionNumber $number,
        private PrescriptionStatus $status,
        private Prescriber $prescriber,
        private PatientSnapshot $patient,
        private OwnerSnapshot $owner,
        private array $lines,
        private ?Validity $validity,
        private Renewals $renewals,
        private array $renewalHistory,
        private ?string $notes,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $cancelledAt,
        private ?string $cancellationReason,
    ) {
    }

    public static function draft(
        PrescriptionId $id,
        ClinicId $clinicId,
        ConsultationId $consultationId,
        Prescriber $prescriber,
        PatientSnapshot $patient,
        OwnerSnapshot $owner,
        \DateTimeImmutable $now,
    ): self {
        $prescription = new self(
            id: $id,
            clinicId: $clinicId,
            consultationId: $consultationId,
            number: null,
            status: PrescriptionStatus::DRAFT,
            prescriber: $prescriber,
            patient: $patient,
            owner: $owner,
            lines: [],
            validity: null,
            renewals: Renewals::none(),
            renewalHistory: [],
            notes: null,
            createdAt: $now,
            updatedAt: $now,
            cancelledAt: null,
            cancellationReason: null,
        );

        $prescription->recordDomainEvent(new PrescriptionDrafted(
            prescriptionId: $id->toString(),
            clinicId: $clinicId->toString(),
            consultationId: $consultationId->toString(),
            draftedAt: $now->format(\DateTimeInterface::ATOM),
        ));

        return $prescription;
    }

    /**
     * @param list<PrescriptionLine> $lines
     * @param list<Renewal>          $renewalHistory
     */
    public static function reconstitute(
        PrescriptionId $id,
        ClinicId $clinicId,
        ConsultationId $consultationId,
        ?PrescriptionNumber $number,
        PrescriptionStatus $status,
        Prescriber $prescriber,
        PatientSnapshot $patient,
        OwnerSnapshot $owner,
        array $lines,
        ?Validity $validity,
        Renewals $renewals,
        array $renewalHistory,
        ?string $notes,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        ?\DateTimeImmutable $cancelledAt,
        ?string $cancellationReason,
    ): self {
        return new self(
            $id,
            $clinicId,
            $consultationId,
            $number,
            $status,
            $prescriber,
            $patient,
            $owner,
            $lines,
            $validity,
            $renewals,
            $renewalHistory,
            $notes,
            $createdAt,
            $updatedAt,
            $cancelledAt,
            $cancellationReason,
        );
    }

    public function addLine(PrescriptionLine $line, \DateTimeImmutable $now): void
    {
        $this->ensureEditable();

        $this->lines[]   = $line;
        $this->updatedAt = $now;

        // A controlled medicine takes precedence over any allowance already
        // set: the strict regime cannot be opted out of.
        if ($line->isControlled()) {
            $this->renewals = Renewals::none();
        }
    }

    public function removeLine(PrescriptionLineId $lineId, \DateTimeImmutable $now): void
    {
        $this->ensureEditable();

        $this->lines = array_values(array_filter(
            $this->lines,
            static fn (PrescriptionLine $line): bool => !$line->id()->equals($lineId),
        ));

        $this->updatedAt = $now;
    }

    public function allowRenewals(int $count, \DateTimeImmutable $now): void
    {
        $this->ensureEditable();

        if ($count > 0 && $this->carriesControlledSubstance()) {
            throw new PrescriptionCannotBeRenewedException(
                $this->id->toString(),
                'it carries a controlled substance',
            );
        }

        $this->renewals  = Renewals::of($count);
        $this->updatedAt = $now;
    }

    public function setNotes(?string $notes, \DateTimeImmutable $now): void
    {
        $this->ensureEditable();

        $notes           = null !== $notes ? mb_trim($notes) : null;
        $this->notes     = '' === $notes ? null : $notes;
        $this->updatedAt = $now;
    }

    public function issue(PrescriptionNumber $number, \DateTimeImmutable $now): void
    {
        $this->ensureEditable();

        if ([] === $this->lines) {
            throw new PrescriptionHasNoLinesException($this->id->toString());
        }

        if (!$this->prescriber->canSign()) {
            throw new PrescriberCannotSignException($this->prescriber->displayName());
        }

        $this->number    = $number;
        $this->status    = PrescriptionStatus::ISSUED;
        $this->validity  = Validity::of($now, $this->validityDays());
        $this->updatedAt = $now;

        $this->recordDomainEvent(new PrescriptionIssued(
            prescriptionId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            consultationId: $this->consultationId->toString(),
            number: $number->toString(),
            lineCount: \count($this->lines),
            carriesControlledSubstance: $this->carriesControlledSubstance(),
            validUntil: $this->validity->validUntil()->format(\DateTimeInterface::ATOM),
            issuedAt: $now->format(\DateTimeInterface::ATOM),
        ));
    }

    public function renew(RenewalId $renewalId, ?string $renewedByUserId, \DateTimeImmutable $now): void
    {
        if (PrescriptionStatus::ISSUED !== $this->status) {
            throw new PrescriptionCannotBeRenewedException(
                $this->id->toString(),
                \sprintf('it is %s', $this->status->value),
            );
        }

        if (null === $this->validity) {
            throw new PrescriptionCannotBeRenewedException($this->id->toString(), 'it has no validity');
        }

        if ($this->validity->isExpiredAt($now)) {
            throw new PrescriptionCannotBeRenewedException($this->id->toString(), 'it has expired');
        }

        if ($this->carriesControlledSubstance()) {
            throw new PrescriptionCannotBeRenewedException(
                $this->id->toString(),
                'it carries a controlled substance',
            );
        }

        if (!$this->renewals->hasRemaining()) {
            throw new PrescriptionCannotBeRenewedException($this->id->toString(), 'no renewal is left');
        }

        $this->renewals         = $this->renewals->consumeOne();
        $this->validity         = $this->validity->extended();
        $this->renewalHistory[] = Renewal::create(
            $renewalId,
            $now,
            $this->validity->validUntil(),
            $renewedByUserId,
        );
        $this->updatedAt = $now;

        $this->recordDomainEvent(new PrescriptionRenewed(
            prescriptionId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            renewalsUsed: $this->renewals->used(),
            renewalsRemaining: $this->renewals->remaining(),
            newValidUntil: $this->validity->validUntil()->format(\DateTimeInterface::ATOM),
            renewedAt: $now->format(\DateTimeInterface::ATOM),
        ));
    }

    public function cancel(string $reason, \DateTimeImmutable $now): void
    {
        if (PrescriptionStatus::CANCELLED === $this->status) {
            throw new PrescriptionCannotBeCancelledException($this->id->toString(), 'it is already cancelled');
        }

        $reason = mb_trim($reason);

        if ('' === $reason) {
            throw new PrescriptionCannotBeCancelledException($this->id->toString(), 'a reason is required');
        }

        $this->status             = PrescriptionStatus::CANCELLED;
        $this->cancellationReason = $reason;
        $this->cancelledAt        = $now;
        $this->updatedAt          = $now;

        $this->recordDomainEvent(new PrescriptionCancelled(
            prescriptionId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            reason: $reason,
            cancelledAt: $now->format(\DateTimeInterface::ATOM),
        ));
    }

    public function carriesControlledSubstance(): bool
    {
        foreach ($this->lines as $line) {
            if ($line->isControlled()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the client could still walk into a pharmacy with it today.
     */
    public function isDispensableAt(\DateTimeImmutable $now): bool
    {
        return PrescriptionStatus::ISSUED === $this->status
            && null !== $this->validity
            && !$this->validity->isExpiredAt($now);
    }

    public function isExpiredAt(\DateTimeImmutable $now): bool
    {
        return PrescriptionStatus::ISSUED === $this->status
            && null !== $this->validity
            && $this->validity->isExpiredAt($now);
    }

    /**
     * How long this prescription runs — the strict regime as soon as one line
     * is controlled, the ordinary one otherwise.
     */
    public function validityDays(): int
    {
        return $this->carriesControlledSubstance()
            ? self::CONTROLLED_VALIDITY_DAYS
            : self::DEFAULT_VALIDITY_DAYS;
    }

    public function id(): PrescriptionId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function consultationId(): ConsultationId
    {
        return $this->consultationId;
    }

    public function number(): ?PrescriptionNumber
    {
        return $this->number;
    }

    public function status(): PrescriptionStatus
    {
        return $this->status;
    }

    public function prescriber(): Prescriber
    {
        return $this->prescriber;
    }

    public function patient(): PatientSnapshot
    {
        return $this->patient;
    }

    public function owner(): OwnerSnapshot
    {
        return $this->owner;
    }

    /**
     * @return list<PrescriptionLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function validity(): ?Validity
    {
        return $this->validity;
    }

    public function renewals(): Renewals
    {
        return $this->renewals;
    }

    /**
     * @return list<Renewal>
     */
    public function renewalHistory(): array
    {
        return $this->renewalHistory;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function cancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function cancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    private function ensureEditable(): void
    {
        if (!$this->status->isEditable()) {
            throw new PrescriptionNotEditableException($this->id->toString(), $this->status);
        }
    }
}
