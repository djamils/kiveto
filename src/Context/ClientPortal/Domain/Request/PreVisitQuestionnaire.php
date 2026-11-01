<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;
use App\Context\ClientPortal\Domain\Exception\QuestionnaireLockedException;

/**
 * The owner's answers before a visit, one per appointment. Editable until
 * it is locked (the appointment started or no longer holds its slot, or a
 * consultation began from it): the lock is recorded (`lockedAt`), so a
 * later reschedule never reopens it. Never resolved by the clinic; closed
 * when the appointment is cancelled or declined, or the account deleted.
 */
final class PreVisitQuestionnaire
{
    /** The only version of the questionnaire (a per-clinic version may come later). */
    public const int VERSION = 1;

    private PortalRequestId $id;
    private string $clinicId;
    private string $clientId;
    private string $userId;
    private string $appointmentId;
    private int $version;
    private QuestionnaireAnswers $answers;
    private RequestStatus $status;
    private \DateTimeImmutable $submittedAt;
    private \DateTimeImmutable $updatedAt;
    private ?\DateTimeImmutable $lockedAt;
    private ?\DateTimeImmutable $closedAt;
    private ?ClosureReason $closedReason;

    private function __construct()
    {
    }

    /**
     * @throws InvalidPortalRequestException when the version is not 1
     */
    public static function submit(
        PortalRequestId $id,
        string $clinicId,
        string $clientId,
        string $userId,
        string $appointmentId,
        int $version,
        QuestionnaireAnswers $answers,
        \DateTimeImmutable $now,
    ): self {
        self::assertVersion($version);

        $questionnaire                = new self();
        $questionnaire->id            = $id;
        $questionnaire->clinicId      = $clinicId;
        $questionnaire->clientId      = $clientId;
        $questionnaire->userId        = $userId;
        $questionnaire->appointmentId = $appointmentId;
        $questionnaire->version       = $version;
        $questionnaire->answers       = $answers;
        $questionnaire->status        = RequestStatus::SUBMITTED;
        $questionnaire->submittedAt   = $now;
        $questionnaire->updatedAt     = $now;
        $questionnaire->lockedAt      = null;
        $questionnaire->closedAt      = null;
        $questionnaire->closedReason  = null;

        return $questionnaire;
    }

    public static function reconstitute(
        PortalRequestId $id,
        string $clinicId,
        string $clientId,
        string $userId,
        string $appointmentId,
        int $version,
        QuestionnaireAnswers $answers,
        RequestStatus $status,
        \DateTimeImmutable $submittedAt,
        \DateTimeImmutable $updatedAt,
        ?\DateTimeImmutable $lockedAt,
        ?\DateTimeImmutable $closedAt,
        ?ClosureReason $closedReason,
    ): self {
        $questionnaire                = new self();
        $questionnaire->id            = $id;
        $questionnaire->clinicId      = $clinicId;
        $questionnaire->clientId      = $clientId;
        $questionnaire->userId        = $userId;
        $questionnaire->appointmentId = $appointmentId;
        $questionnaire->version       = $version;
        $questionnaire->answers       = $answers;
        $questionnaire->status        = $status;
        $questionnaire->submittedAt   = $submittedAt;
        $questionnaire->updatedAt     = $updatedAt;
        $questionnaire->lockedAt      = $lockedAt;
        $questionnaire->closedAt      = $closedAt;
        $questionnaire->closedReason  = $closedReason;

        return $questionnaire;
    }

    /**
     * Replaces the answers (whoever of the account answers last wins).
     *
     * @throws QuestionnaireLockedException  when locked or closed
     * @throws InvalidPortalRequestException when the version is not 1
     */
    public function answer(int $version, string $userId, QuestionnaireAnswers $answers, \DateTimeImmutable $now): void
    {
        if (!$this->isEditable()) {
            throw new QuestionnaireLockedException($this->appointmentId);
        }

        self::assertVersion($version);

        $this->version   = $version;
        $this->userId    = $userId;
        $this->answers   = $answers;
        $this->updatedAt = $now;
    }

    /**
     * Freezes the answers for good.
     *
     * @return bool false when already locked (nothing changed)
     */
    public function lock(\DateTimeImmutable $now): bool
    {
        if (null !== $this->lockedAt) {
            return false;
        }

        $this->lockedAt = $now;

        return true;
    }

    /** @return bool false when already closed (nothing changed) */
    public function close(ClosureReason $reason, \DateTimeImmutable $now): bool
    {
        if (RequestStatus::CLOSED === $this->status) {
            return false;
        }

        $this->status       = RequestStatus::CLOSED;
        $this->closedAt     = $now;
        $this->closedReason = $reason;
        $this->updatedAt    = $now;

        return true;
    }

    /** Account deletion: the owner's texts erased. */
    public function eraseFreeText(): void
    {
        $this->answers = $this->answers->withoutFreeText();
    }

    public function isSubmitted(): bool
    {
        return RequestStatus::SUBMITTED === $this->status;
    }

    public function isEditable(): bool
    {
        return RequestStatus::SUBMITTED === $this->status && null === $this->lockedAt;
    }

    public function id(): PortalRequestId
    {
        return $this->id;
    }

    public function clinicId(): string
    {
        return $this->clinicId;
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function appointmentId(): string
    {
        return $this->appointmentId;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function answers(): QuestionnaireAnswers
    {
        return $this->answers;
    }

    public function status(): RequestStatus
    {
        return $this->status;
    }

    public function submittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function lockedAt(): ?\DateTimeImmutable
    {
        return $this->lockedAt;
    }

    public function closedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function closedReason(): ?ClosureReason
    {
        return $this->closedReason;
    }

    private static function assertVersion(int $version): void
    {
        if (self::VERSION !== $version) {
            throw new InvalidPortalRequestException('version', \sprintf('Version de questionnaire non prise en charge : %d.', $version));
        }
    }
}
