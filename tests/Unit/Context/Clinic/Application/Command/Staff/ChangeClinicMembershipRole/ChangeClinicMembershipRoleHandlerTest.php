<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipRole;

use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipRole\ChangeClinicMembershipRole;
use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipRole\ChangeClinicMembershipRoleHandler;
use App\Context\Clinic\Application\Exception\CannotChangeRoleWhileVeterinaryCredentialsExist;
use App\Context\Clinic\Application\Port\StaffProfileReadRepositoryInterface;
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
use PHPUnit\Framework\TestCase;

final class ChangeClinicMembershipRoleHandlerTest extends TestCase
{
    private const string MEMBERSHIP_ID = '01912345-6789-7abc-8def-000000000001';

    public function testChangesRoleSuccessfullyWhenNoCredentials(): void
    {
        $membership = $this->makeMembership(ClinicMemberRole::VETERINARY);

        $repo = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repo->method('findById')->willReturn($membership);
        $repo->expects(self::once())->method('save');

        $profileReadRepo = $this->createStub(StaffProfileReadRepositoryInterface::class);
        $profileReadRepo->method('hasVeterinaryCredentialsFor')->willReturn(false);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $handler = new ChangeClinicMembershipRoleHandler($repo, $profileReadRepo, new DomainEventPublisher($eventBus));

        $handler(new ChangeClinicMembershipRole(self::MEMBERSHIP_ID, ClinicMemberRole::MANAGER));

        self::assertSame(ClinicMemberRole::MANAGER, $membership->role());
    }

    public function testThrowsWhenCredentialsExistAndNewRoleCannotHoldThem(): void
    {
        $membership = $this->makeMembership(ClinicMemberRole::VETERINARY);

        $repo = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repo->method('findById')->willReturn($membership);
        $repo->expects(self::never())->method('save');

        $profileReadRepo = $this->createStub(StaffProfileReadRepositoryInterface::class);
        $profileReadRepo->method('hasVeterinaryCredentialsFor')->willReturn(true);

        $publisher = new DomainEventPublisher($this->createStub(EventBusInterface::class));
        $handler   = new ChangeClinicMembershipRoleHandler($repo, $profileReadRepo, $publisher);

        $this->expectException(CannotChangeRoleWhileVeterinaryCredentialsExist::class);

        $handler(new ChangeClinicMembershipRole(self::MEMBERSHIP_ID, ClinicMemberRole::MANAGER));
    }

    public function testThrowsWhenNotFound(): void
    {
        $repo = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $profileReadRepo = $this->createStub(StaffProfileReadRepositoryInterface::class);
        $publisher       = new DomainEventPublisher($this->createStub(EventBusInterface::class));
        $handler         = new ChangeClinicMembershipRoleHandler($repo, $profileReadRepo, $publisher);

        $this->expectException(\InvalidArgumentException::class);
        $handler(new ChangeClinicMembershipRole(self::MEMBERSHIP_ID, ClinicMemberRole::MANAGER));
    }

    private function makeMembership(ClinicMemberRole $role = ClinicMemberRole::VETERINARY): ClinicMembership
    {
        return ClinicMembership::reconstitute(
            id: ClinicMembershipId::fromString(self::MEMBERSHIP_ID),
            clinicId: ClinicId::fromString('01912345-6789-7abc-8def-000000000002'),
            userId: UserId::fromString('01912345-6789-7abc-8def-000000000003'),
            role: $role,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2026-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2026-01-01'),
            isDefault: false,
        );
    }
}
