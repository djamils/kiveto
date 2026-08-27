<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Service;

use App\Context\Clinic\Application\Exception\LastClinicAdministratorException;
use App\Context\Clinic\Application\Service\ClinicAdministrationInvariant;
use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

/**
 * The four branches of the no-worsening rule.
 */
final class ClinicAdministrationInvariantTest extends TestCase
{
    private const string ID        = '01912345-6789-7abc-8def-000000000001';
    private const string CLINIC_ID = '01912345-6789-7abc-8def-000000000002';
    private const string USER_ID   = '01912345-6789-7abc-8def-000000000003';

    private const string NOW = '2026-06-01 10:00:00';

    public function testAllowsWhenTheMembershipIsStillAnEffectiveAdministrator(): void
    {
        $membership = $this->membership(isClinicAdministrator: true);

        // Never consulted: the post-state already satisfies the rule.
        $repository = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repository->expects(self::never())->method('countEffectiveAdministrators');

        $this->invariant($repository)->assertNoKeyLost($membership, true, $this->now());
    }

    public function testAllowsWhenSomebodyElseIsAnEffectiveAdministrator(): void
    {
        $membership = $this->membership();

        $repository = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $repository->method('countEffectiveAdministrators')->willReturn(1);

        $this->invariant($repository)->assertNoKeyLost($membership, true, $this->now());

        $this->expectNotToPerformAssertions();
    }

    /**
     * The anti-trap case. A clinic whose only administrator expired on its own
     * must still be repairable from inside; refusing every write there would
     * turn the protection into a lock nobody can open.
     */
    public function testAllowsWhenTheMembershipWasNotAnAdministratorBeforeTheWrite(): void
    {
        $membership = $this->membership();

        $repository = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repository->expects(self::never())->method('countEffectiveAdministrators');

        $this->invariant($repository)->assertNoKeyLost($membership, false, $this->now());
    }

    public function testRefusesWhenTheWriteRemovesTheLastEffectiveAdministrator(): void
    {
        $membership = $this->membership();

        $repository = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $repository->method('countEffectiveAdministrators')->willReturn(0);

        $this->expectException(LastClinicAdministratorException::class);

        $this->invariant($repository)->assertNoKeyLost($membership, true, $this->now());
    }

    public function testExcludesTheMutatedMembershipFromTheCount(): void
    {
        $membership = $this->membership();

        $repository = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('countEffectiveAdministrators')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (\DateTimeImmutable $now): bool => self::NOW === $now->format('Y-m-d H:i:s')),
                self::callback(static fn (?ClinicMembershipId $id): bool => null !== $id && self::ID === $id->toString()),
            )
            ->willReturn(1)
        ;

        $this->invariant($repository)->assertNoKeyLost($membership, true, $this->now());
    }

    private function invariant(ClinicMembershipRepositoryInterface $repository): ClinicAdministrationInvariant
    {
        return new ClinicAdministrationInvariant($repository);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }

    private function membership(bool $isClinicAdministrator = false): ClinicMembership
    {
        return ClinicMembership::reconstitute(
            id: ClinicMembershipId::fromString(self::ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            userId: UserId::fromString(self::USER_ID),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2026-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2026-01-01'),
            isDefault: false,
            isClinicAdministrator: $isClinicAdministrator,
        );
    }
}
