<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Service;

use App\Context\Clinic\Application\Exception\LastClinicAdministratorException;
use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;

/**
 * No write may take a clinic from "has an effective administrator" to "has
 * none".
 *
 * Deliberately a no-worsening rule rather than a no-zero rule. A validity
 * window closes on its own, with no write to mediate it, so a clinic can reach
 * zero administrators without anybody doing anything — and a rule that refused
 * every write in that state would leave nobody able to repair it.
 */
final readonly class ClinicAdministrationInvariant
{
    public function __construct(
        private ClinicMembershipRepositoryInterface $memberships,
    ) {
    }

    /**
     * @param bool               $wasEffectiveAdministrator the membership's state BEFORE the mutation
     * @param \DateTimeImmutable $now                       the instant the caller judged that state at
     *
     * @throws LastClinicAdministratorException
     */
    public function assertNoKeyLost(
        ClinicMembership $mutated,
        bool $wasEffectiveAdministrator,
        \DateTimeImmutable $now,
    ): void {
        // We cannot take away what this membership never held.
        if (!$wasEffectiveAdministrator) {
            return;
        }

        if ($mutated->isEffectiveAdministratorAt($now)) {
            return;
        }

        $others = $this->memberships->countEffectiveAdministrators(
            $mutated->clinicId(),
            $now,
            $mutated->id(),
        );

        if (0 === $others) {
            throw new LastClinicAdministratorException($mutated->clinicId()->toString());
        }
    }
}
