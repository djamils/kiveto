<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Jurisdiction\France;

use App\Context\Regulatory\Domain\Policy\RegulatoryPolicyInterface;
use App\Context\Regulatory\Domain\Service\WorkingDayCalculatorInterface;

/**
 * French regulatory policy for stray animal management.
 *
 * Governing texts:
 *   - Code rural et de la pêche maritime (CRPM), articles L211-24 à L211-28
 *   - Arrêté du 8 juin 2006 relatif aux fourrières pour animaux
 *
 * If a deadline changes (e.g. legislative reform), update the constant and its
 * docblock, bump the class-level revision note, and add a test in FrancePolicyTest.
 */
final readonly class FranceRegulatoryPolicy implements RegulatoryPolicyInterface
{
    /**
     * CRPM L211-26 al. 1 — owner notification deadline: 48 calendar hours.
     * "Le gestionnaire de la fourrière avise dans les quarante-huit heures…"
     * Calendar hours, not working hours. Starts at the moment of intake.
     */
    private const int AUTHORITY_NOTIFICATION_HOURS = 48;

    /**
     * CRPM L211-25 al. 2 — minimum statutory holding period: 8 working days.
     * "…pendant au moins huit jours ouvrés à compter du jour de la capture."
     * Working days exclude weekends and French public holidays (jours fériés).
     * After this deadline the animal may be assigned, adopted, or euthanised.
     */
    private const int STRAY_CUSTODY_WORKING_DAYS = 8;

    public function __construct(private WorkingDayCalculatorInterface $calculator)
    {
    }

    public function getAuthorityNotificationDeadline(\DateTimeImmutable $admissionOpenedAt): \DateTimeImmutable
    {
        return $admissionOpenedAt->modify(\sprintf('+%d hours', self::AUTHORITY_NOTIFICATION_HOURS));
    }

    public function getStrayCustodyDeadline(\DateTimeImmutable $admissionOpenedAt): \DateTimeImmutable
    {
        return $this->calculator->addWorkingDays($admissionOpenedAt, self::STRAY_CUSTODY_WORKING_DAYS);
    }
}
