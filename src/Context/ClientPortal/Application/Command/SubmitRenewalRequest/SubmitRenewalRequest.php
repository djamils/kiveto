<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\SubmitRenewalRequest;

use App\Shared\Application\Bus\CommandInterface;

/**
 * `POST …/prescriptions/{id}/renewal-requests`: asks the clinic to renew a
 * whole prescription. Answers the request id — the one already submitted on
 * that prescription when there is one (status `submitted` either way). The
 * 10-per-24-hours quota is the caller's (`PortalRequestQuotaInterface`).
 *
 * @throws \App\Context\ClientPortal\Domain\Exception\PrescriptionNotFoundException     not a listed document of the account (404)
 * @throws \App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException     petCondition, note (422)
 * @throws \App\Context\ClientPortal\Domain\Exception\PrescriptionNotRenewableException S6c (422 `prescription_not_renewable`, `reason`)
 */
final readonly class SubmitRenewalRequest implements CommandInterface
{
    /**
     * @param string $petCondition improved|no_improvement|adverse_effects
     */
    public function __construct(
        public string $userId,
        public string $clinicId,
        public string $prescriptionId,
        public string $petCondition,
        public ?string $note = null,
    ) {
    }
}
