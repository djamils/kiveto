<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Jurisdiction\France;

use App\Context\Regulatory\Domain\Policy\RegulatoryPolicyInterface;
use App\Context\Regulatory\Domain\Service\WorkingDayCalculatorInterface;

final class FranceRegulatoryPolicy implements RegulatoryPolicyInterface
{
    public function __construct(private readonly WorkingDayCalculatorInterface $calculator)
    {
    }

    public function getAuthorityNotificationDeadline(\DateTimeImmutable $admissionOpenedAt): \DateTimeImmutable
    {
        return $admissionOpenedAt->modify('+48 hours');
    }

    public function getStrayCustodyDeadline(\DateTimeImmutable $admissionOpenedAt): \DateTimeImmutable
    {
        return $this->calculator->addWorkingDays($admissionOpenedAt, 8);
    }
}
