<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\DisableClinicMembership;

use App\Context\Clinic\Application\Command\Staff\DisableClinicMembership\DisableClinicMembership;
use App\Context\Clinic\Application\Command\Staff\DisableClinicMembership\DisableClinicMembershipHandler;
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

final class DisableClinicMembershipHandlerTest extends TestCase
{
    private const string MEMBERSHIP_ID = '01912345-6789-7abc-8def-000000000001';

    public function testDisablesActiveMembership(): void
    {
        $membership = ClinicMembership::reconstitute(
            id: ClinicMembershipId::fromString(self::MEMBERSHIP_ID),
            clinicId: ClinicId::fromString('01912345-6789-7abc-8def-000000000002'),
            userId: UserId::fromString('01912345-6789-7abc-8def-000000000003'),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2026-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2026-01-01'),
            isDefault: false,
            isClinicAdministrator: false,
        );

        $repo = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repo->method('findById')->willReturn($membership);
        $repo->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $handler = new DisableClinicMembershipHandler(
            $repo,
            new ClinicAdministrationInvariant($repo),
            $this->clock(),
            new DomainEventPublisher($eventBus),
        );
        $handler(new DisableClinicMembership(self::MEMBERSHIP_ID));

        self::assertSame(ClinicMembershipStatus::DISABLED, $membership->status());
    }

    public function testThrowsWhenNotFound(): void
    {
        $repo = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $publisher = new DomainEventPublisher($this->createStub(EventBusInterface::class));
        $handler   = new DisableClinicMembershipHandler(
            $repo,
            new ClinicAdministrationInvariant($repo),
            $this->clock(),
            $publisher,
        );

        $this->expectException(\InvalidArgumentException::class);
        $handler(new DisableClinicMembership(self::MEMBERSHIP_ID));
    }

    public function testRefusesToDisableTheLastEffectiveAdministrator(): void
    {
        $membership = $this->membership(isClinicAdministrator: true);

        $repo = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repo->method('findById')->willReturn($membership);
        $repo->method('countEffectiveAdministrators')->willReturn(0);
        $repo->expects(self::never())->method('save');

        // The handler mutates before it asserts, so the guarantee is that the
        // change never reaches the repository — not that the in-memory
        // aggregate is untouched.
        $this->expectException(LastClinicAdministratorException::class);

        $handler = new DisableClinicMembershipHandler(
            $repo,
            new ClinicAdministrationInvariant($repo),
            $this->clock(),
            new DomainEventPublisher($this->createStub(EventBusInterface::class)),
        );

        $handler(new DisableClinicMembership(self::MEMBERSHIP_ID));
    }

    public function testDisablesTheLastAdministratorWhenAnotherOneRemains(): void
    {
        $membership = $this->membership(isClinicAdministrator: true);

        $repo = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repo->method('findById')->willReturn($membership);
        $repo->method('countEffectiveAdministrators')->willReturn(1);
        $repo->expects(self::once())->method('save');

        $handler = new DisableClinicMembershipHandler(
            $repo,
            new ClinicAdministrationInvariant($repo),
            $this->clock(),
            new DomainEventPublisher($this->createStub(EventBusInterface::class)),
        );

        $handler(new DisableClinicMembership(self::MEMBERSHIP_ID));

        self::assertSame(ClinicMembershipStatus::DISABLED, $membership->status());
    }

    /**
     * The anti-trap case: a clinic already at zero administrators must still be
     * able to disable an unrelated member, or nobody can repair anything.
     */
    public function testDisablesAnOrdinaryMemberInAClinicThatAlreadyHasNoAdministrator(): void
    {
        $membership = $this->membership();

        $repo = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repo->method('findById')->willReturn($membership);
        $repo->method('countEffectiveAdministrators')->willReturn(0);
        $repo->expects(self::once())->method('save');

        $handler = new DisableClinicMembershipHandler(
            $repo,
            new ClinicAdministrationInvariant($repo),
            $this->clock(),
            new DomainEventPublisher($this->createStub(EventBusInterface::class)),
        );

        $handler(new DisableClinicMembership(self::MEMBERSHIP_ID));

        self::assertSame(ClinicMembershipStatus::DISABLED, $membership->status());
    }

    private function membership(bool $isClinicAdministrator = false): ClinicMembership
    {
        return ClinicMembership::reconstitute(
            id: ClinicMembershipId::fromString(self::MEMBERSHIP_ID),
            clinicId: ClinicId::fromString('01912345-6789-7abc-8def-000000000002'),
            userId: UserId::fromString('01912345-6789-7abc-8def-000000000003'),
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

    private function clock(): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01 10:00:00'));

        return $clock;
    }
}
