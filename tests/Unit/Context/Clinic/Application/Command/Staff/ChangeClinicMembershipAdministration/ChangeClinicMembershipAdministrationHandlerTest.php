<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipAdministration;

use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipAdministration\ChangeClinicMembershipAdministration;
use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipAdministration\ChangeClinicMembershipAdministrationHandler;
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
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class ChangeClinicMembershipAdministrationHandlerTest extends TestCase
{
    private const string ID           = '01912345-6789-7abc-8def-000000000001';
    private const string CLINIC_ID    = '01912345-6789-7abc-8def-000000000002';
    private const string OTHER_CLINIC = '01912345-6789-7abc-8def-00000000000a';
    private const string USER_ID      = '01912345-6789-7abc-8def-000000000003';

    public function testGrantsTheHat(): void
    {
        $membership = $this->membership();

        $repository = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repository->method('findById')->willReturn($membership);
        $repository->method('countEffectiveAdministrators')->willReturn(1);
        $repository->expects(self::once())->method('save')->with($membership);

        $this->handler($repository)($this->command(true));

        self::assertTrue($membership->isClinicAdministrator());
    }

    public function testRevokesTheHatWhenSomebodyElseRemains(): void
    {
        $membership = $this->membership(isClinicAdministrator: true);

        $repository = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repository->method('findById')->willReturn($membership);
        $repository->method('countEffectiveAdministrators')->willReturn(1);
        $repository->expects(self::once())->method('save')->with($membership);

        $this->handler($repository)($this->command(false));

        self::assertFalse($membership->isClinicAdministrator());
    }

    public function testRefusesToRevokeFromTheLastAdministratorAndNeverSaves(): void
    {
        $membership = $this->membership(isClinicAdministrator: true);

        $repository = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repository->method('findById')->willReturn($membership);
        $repository->method('countEffectiveAdministrators')->willReturn(0);
        $repository->expects(self::never())->method('save');

        $this->expectException(LastClinicAdministratorException::class);

        $this->handler($repository)($this->command(false));
    }

    public function testRevokingFromAMemberWithoutTheHatIsASilentNoOp(): void
    {
        $membership = $this->membership();

        $repository = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repository->method('findById')->willReturn($membership);
        // Nothing is counted and nothing is written: the hat is already off.
        $repository->expects(self::never())->method('countEffectiveAdministrators');
        $repository->expects(self::never())->method('save');

        $this->handler($repository)($this->command(false));

        self::assertFalse($membership->isClinicAdministrator());
        self::assertCount(0, $membership->pullDomainEvents());
    }

    public function testGrantingToAMemberWhoAlreadyHasItRecordsNoSecondEvent(): void
    {
        $membership = $this->membership(isClinicAdministrator: true);

        $repository = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repository->method('findById')->willReturn($membership);
        $repository->expects(self::never())->method('save');

        $this->handler($repository)($this->command(true));

        self::assertTrue($membership->isClinicAdministrator());
        self::assertCount(0, $membership->pullDomainEvents());
    }

    /**
     * The membership id comes off a URL; a manager of clinic A must not be able
     * to hand out manage_staff in clinic B by editing it.
     */
    public function testRefusesAMembershipBelongingToAnotherClinic(): void
    {
        $membership = $this->membership(clinicId: self::OTHER_CLINIC);

        $repository = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repository->method('findById')->willReturn($membership);
        $repository->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $this->handler($repository)($this->command(true));
    }

    public function testThrowsWhenTheMembershipDoesNotExist(): void
    {
        $repository = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $this->handler($repository)($this->command(true));
    }

    private function command(bool $isClinicAdministrator): ChangeClinicMembershipAdministration
    {
        return new ChangeClinicMembershipAdministration(
            clinicId: self::CLINIC_ID,
            membershipId: self::ID,
            isClinicAdministrator: $isClinicAdministrator,
        );
    }

    private function handler(
        ClinicMembershipRepositoryInterface $repository,
    ): ChangeClinicMembershipAdministrationHandler {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01 10:00:00'));

        return new ChangeClinicMembershipAdministrationHandler(
            $repository,
            new ClinicAdministrationInvariant($repository),
            $clock,
            new DomainEventPublisher($this->createStub(EventBusInterface::class)),
        );
    }

    private function membership(
        bool $isClinicAdministrator = false,
        string $clinicId = self::CLINIC_ID,
    ): ClinicMembership {
        return ClinicMembership::reconstitute(
            id: ClinicMembershipId::fromString(self::ID),
            clinicId: ClinicId::fromString($clinicId),
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
