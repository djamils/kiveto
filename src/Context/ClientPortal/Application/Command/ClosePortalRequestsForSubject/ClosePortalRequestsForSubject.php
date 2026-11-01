<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ClosePortalRequestsForSubject;

use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\RequestSubject;
use App\Shared\Application\Bus\CommandInterface;

/**
 * Closes the open requests hanging on a subject, without notifying anyone:
 * a client archived (`client` / `client_archived`), a prescription
 * cancelled (`prescription` / `prescription_cancelled`), an appointment
 * cancelled or declined (`appointment` / `appointment_cancelled` |
 * `appointment_declined`, questionnaires), a pet archived or deceased
 * (`pet`). Idempotent. Answers how many requests were closed.
 */
final readonly class ClosePortalRequestsForSubject implements CommandInterface
{
    /**
     * @param ?string $clinicId the subject's clinic; ignored for an account (`user`)
     */
    public function __construct(
        public RequestSubject $subject,
        public string $subjectId,
        public ClosureReason $reason,
        public ?string $clinicId,
    ) {
    }
}
