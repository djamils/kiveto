<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Regulatory\Policy;

use App\Context\Regulatory\Domain\Policy\RegulatoryPolicyInterface;
use PHPUnit\Framework\TestCase;

abstract class JurisdictionPolicyContractCase extends TestCase
{
    public function testAuthorityNotificationDeadlineIsStrictlyAfterAdmissionDate(): void
    {
        $policy    = $this->createPolicy();
        $admission = new \DateTimeImmutable('2026-05-01T10:00:00Z');
        $deadline  = $policy->getAuthorityNotificationDeadline($admission);

        self::assertGreaterThan($admission, $deadline);
    }

    public function testStrayCustodyDeadlineIsStrictlyAfterAdmissionDate(): void
    {
        $policy    = $this->createPolicy();
        $admission = new \DateTimeImmutable('2026-05-01T10:00:00Z');
        $deadline  = $policy->getStrayCustodyDeadline($admission);

        self::assertGreaterThan($admission, $deadline);
    }

    public function testDeadlinesDifferForTwoConsecutiveDays(): void
    {
        $policy = $this->createPolicy();
        // Use regular weekdays with no holidays nearby to avoid edge cases
        $day1 = new \DateTimeImmutable('2026-06-01T10:00:00Z');
        $day2 = new \DateTimeImmutable('2026-06-02T10:00:00Z');

        $custodyDeadline1 = $policy->getStrayCustodyDeadline($day1);
        $custodyDeadline2 = $policy->getStrayCustodyDeadline($day2);

        self::assertNotSame($custodyDeadline1->format('Y-m-d'), $custodyDeadline2->format('Y-m-d'));
    }

    abstract protected function createPolicy(): RegulatoryPolicyInterface;
}
