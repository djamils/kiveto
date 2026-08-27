<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Staff\ListPermissionsForUserInClinic;

use App\Context\Clinic\Application\Query\Staff\ListPermissionsForUserInClinic\ListPermissionsForUserInClinic;
use App\Context\Clinic\Application\Query\Staff\ListPermissionsForUserInClinic\ListPermissionsForUserInClinicHandler;
use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

/**
 * The single seam where a member's rights are resolved. Every settings
 * controller and ClinicPermissionGuard rests on the four branches below.
 */
final class ListPermissionsForUserInClinicHandlerTest extends TestCase
{
    private const string CLINIC_ID = '01912345-6789-7abc-8def-000000000002';
    private const string USER_ID   = '01912345-6789-7abc-8def-000000000003';

    public function testReturnsNothingWhenTheUserHasNoMembership(): void
    {
        self::assertSame([], $this->resolve(null));
    }

    public function testReturnsNothingWhenTheMembershipIsDisabled(): void
    {
        $membership = $this->membership(
            status: ClinicMembershipStatus::DISABLED,
            isClinicAdministrator: true,
        );

        self::assertSame([], $this->resolve($membership));
    }

    public function testReturnsNothingWhenTheMembershipHasExpired(): void
    {
        $membership = $this->membership(
            validUntil: new \DateTimeImmutable('2026-03-01'),
            isClinicAdministrator: true,
        );

        self::assertSame([], $this->resolve($membership));
    }

    public function testReturnsNothingWhenTheMembershipHasNotStarted(): void
    {
        $membership = $this->membership(
            validFrom: new \DateTimeImmutable('2026-09-01'),
            isClinicAdministrator: true,
        );

        self::assertSame([], $this->resolve($membership));
    }

    public function testVeterinaryWithoutTheHatCannotManageStaff(): void
    {
        $permissions = $this->resolve($this->membership());

        self::assertContains('create_prescription', $permissions);
        self::assertNotContains('manage_staff', $permissions);
        self::assertNotContains('manage_billing', $permissions);
    }

    /**
     * The case the change exists for: the owner-veterinarian prescribes and
     * configures the clinic, with no duplicate in the resolved list.
     */
    public function testVeterinaryWithTheHatCanPrescribeAndManageStaff(): void
    {
        $permissions = $this->resolve($this->membership(isClinicAdministrator: true));

        self::assertContains('create_prescription', $permissions);
        self::assertContains('manage_staff', $permissions);
        self::assertContains('manage_billing', $permissions);
        self::assertSame(\count($permissions), \count(array_unique($permissions)));
    }

    public function testManagerWithoutTheHatKeepsClinicalRightsOnly(): void
    {
        $permissions = $this->resolve($this->membership(role: ClinicMemberRole::MANAGER));

        self::assertContains('create_prescription', $permissions);
        self::assertContains('create_consultation', $permissions);
        self::assertContains('view_medical_record', $permissions);
        self::assertNotContains('manage_staff', $permissions);
        self::assertNotContains('manage_billing', $permissions);
    }

    /**
     * @return list<string>
     */
    private function resolve(?ClinicMembership $membership): array
    {
        $repository = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $repository->method('findByClinicAndUser')->willReturn($membership);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01'));

        $handler = new ListPermissionsForUserInClinicHandler($repository, $clock);

        return $handler(new ListPermissionsForUserInClinic(
            userId: self::USER_ID,
            clinicId: self::CLINIC_ID,
        ));
    }

    private function membership(
        ClinicMemberRole $role = ClinicMemberRole::VETERINARY,
        ClinicMembershipStatus $status = ClinicMembershipStatus::ACTIVE,
        ?\DateTimeImmutable $validFrom = null,
        ?\DateTimeImmutable $validUntil = null,
        bool $isClinicAdministrator = false,
    ): ClinicMembership {
        return ClinicMembership::reconstitute(
            id: ClinicMembershipId::fromString('01912345-6789-7abc-8def-000000000001'),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            userId: UserId::fromString(self::USER_ID),
            role: $role,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: $status,
            validFrom: $validFrom ?? new \DateTimeImmutable('2026-01-01'),
            validUntil: $validUntil,
            createdAt: new \DateTimeImmutable('2026-01-01'),
            isDefault: false,
            isClinicAdministrator: $isClinicAdministrator,
        );
    }
}
