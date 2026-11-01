<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\PetChangeRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequestRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaireRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\RequestSubject;
use App\Shared\Domain\Time\ClockInterface;

/**
 * Closes the open requests hanging on a subject (S6a): never notified, out
 * of the clinic's queue, idempotent. The account deletion also erases the
 * owner's free text of every request of the account.
 */
final readonly class PortalRequestCloser
{
    public function __construct(
        private PreVisitQuestionnaireRepositoryInterface $questionnaires,
        private PetChangeRequestRepositoryInterface $changes,
        private PrescriptionRenewalRequestRepositoryInterface $renewals,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param ?string $clinicId the clinic of the subject (ignored for an account)
     *
     * @return int how many requests were closed
     */
    public function close(RequestSubject $subject, string $subjectId, ClosureReason $reason, ?string $clinicId): int
    {
        $now    = $this->clock->now();
        $closed = 0;

        foreach ($this->questionnaires->findSubmittedForSubjectForUpdate($subject, $subjectId, $clinicId) as $questionnaire) {
            $questionnaire->close($reason, $now);
            $this->questionnaires->save($questionnaire);
            ++$closed;
        }

        foreach ($this->changes->findSubmittedForSubjectForUpdate($subject, $subjectId, $clinicId) as $change) {
            $change->close($reason, $now);
            $this->changes->save($change);
            ++$closed;
        }

        foreach ($this->renewals->findSubmittedForSubjectForUpdate($subject, $subjectId, $clinicId) as $renewal) {
            $renewal->close($reason, $now);
            $this->renewals->save($renewal);
            ++$closed;
        }

        return $closed;
    }

    /** Account deletion: open requests closed with `account_deleted`, every request's free text erased. */
    public function eraseAccount(string $userId): void
    {
        $this->close(RequestSubject::USER, $userId, ClosureReason::ACCOUNT_DELETED, null);

        foreach ($this->questionnaires->findByUser($userId) as $questionnaire) {
            $questionnaire->eraseFreeText();
            $this->questionnaires->save($questionnaire);
        }

        foreach ($this->changes->findByUser($userId) as $change) {
            $change->eraseFreeText();
            $this->changes->save($change);
        }

        foreach ($this->renewals->findByUser($userId) as $renewal) {
            $renewal->eraseFreeText();
            $this->renewals->save($renewal);
        }
    }
}
