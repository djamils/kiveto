<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Account;

use App\Context\Clinic\Domain\ValueObject\ClinicId;

/**
 * The clinic as a customer of Kiveto, seen from the console: its plan and
 * the end of its pilot, the software it runs alongside, the team's internal
 * note, when it finished its onboarding, and why it is suspended. The
 * clinic's own status (active, suspended) stays on Clinic.
 */
final class ClinicAccount
{
    private const int MAX_NOTE_LENGTH = 2000;

    private ClinicId $clinicId;
    private ClinicPlan $plan;
    private ?\DateTimeImmutable $pilotEndsOn;
    private ?ParallelSoftware $parallelSoftware;
    private string $internalNote;
    private ?\DateTimeImmutable $onboardedAt;
    private ?ClinicSuspension $suspension;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    private function __construct()
    {
    }

    public static function open(
        ClinicId $clinicId,
        ClinicPlan $plan,
        ?\DateTimeImmutable $pilotEndsOn,
        ?ParallelSoftware $parallelSoftware,
        \DateTimeImmutable $at,
    ): self {
        return self::reconstitute($clinicId, $plan, self::pilotEnd($plan, $pilotEndsOn), $parallelSoftware, '', null, null, $at, $at);
    }

    /**
     * The account of a clinic created before the console: a pilot without
     * end date, never edited.
     */
    public static function defaultFor(ClinicId $clinicId, \DateTimeImmutable $createdAt): self
    {
        return self::reconstitute($clinicId, ClinicPlan::PILOT, null, null, '', null, null, $createdAt, $createdAt);
    }

    public static function reconstitute(
        ClinicId $clinicId,
        ClinicPlan $plan,
        ?\DateTimeImmutable $pilotEndsOn,
        ?ParallelSoftware $parallelSoftware,
        string $internalNote,
        ?\DateTimeImmutable $onboardedAt,
        ?ClinicSuspension $suspension,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $account                   = new self();
        $account->clinicId         = $clinicId;
        $account->plan             = $plan;
        $account->pilotEndsOn      = $pilotEndsOn;
        $account->parallelSoftware = $parallelSoftware;
        $account->internalNote     = $internalNote;
        $account->onboardedAt      = $onboardedAt;
        $account->suspension       = $suspension;
        $account->createdAt        = $createdAt;
        $account->updatedAt        = $updatedAt;

        return $account;
    }

    /**
     * « Modifier la clinique · Abonnement, Logiciel en parallèle, Note interne ».
     */
    public function revise(
        ClinicPlan $plan,
        ?\DateTimeImmutable $pilotEndsOn,
        ?ParallelSoftware $parallelSoftware,
        string $internalNote,
        \DateTimeImmutable $at,
    ): void {
        $this->plan             = $plan;
        $this->pilotEndsOn      = self::pilotEnd($plan, $pilotEndsOn);
        $this->parallelSoftware = $parallelSoftware;
        $this->internalNote     = mb_substr(trim($internalNote), 0, self::MAX_NOTE_LENGTH);
        $this->updatedAt        = $at;
    }

    /**
     * Every counted onboarding step is done: the clinic becomes an active
     * pilot, once and for good.
     */
    public function markOnboarded(\DateTimeImmutable $at): bool
    {
        if (null !== $this->onboardedAt) {
            return false;
        }

        $this->onboardedAt = $at;
        $this->updatedAt   = $at;

        return true;
    }

    public function recordSuspension(ClinicSuspension $suspension): void
    {
        $this->suspension = $suspension;
        $this->updatedAt  = $suspension->suspendedAt();
    }

    public function clearSuspension(\DateTimeImmutable $at): void
    {
        $this->suspension = null;
        $this->updatedAt  = $at;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function plan(): ClinicPlan
    {
        return $this->plan;
    }

    public function pilotEndsOn(): ?\DateTimeImmutable
    {
        return $this->pilotEndsOn;
    }

    public function parallelSoftware(): ?ParallelSoftware
    {
        return $this->parallelSoftware;
    }

    public function internalNote(): string
    {
        return $this->internalNote;
    }

    public function onboardedAt(): ?\DateTimeImmutable
    {
        return $this->onboardedAt;
    }

    public function suspension(): ?ClinicSuspension
    {
        return $this->suspension;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Only a pilot ends.
     */
    private static function pilotEnd(ClinicPlan $plan, ?\DateTimeImmutable $pilotEndsOn): ?\DateTimeImmutable
    {
        return ClinicPlan::PILOT === $plan ? $pilotEndsOn?->setTime(0, 0) : null;
    }
}
