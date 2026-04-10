<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipRole;

use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipRole\ChangeClinicMembershipRole;
use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipRole\ChangeClinicMembershipRoleHandler;
use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use PHPUnit\Framework\TestCase;

final class ChangeClinicMembershipRoleHandlerTest extends TestCase
{
    private const string MEMBERSHIP_ID = '01912345-6789-7abc-8def-000000000001';

    public function testChangesRoleSuccessfully(): void
    {
        $membership = ClinicMembership::reconstitute(
            id: ClinicMembershipId::fromString(self::MEMBERSHIP_ID),
            clinicId: ClinicId::fromString('01912345-6789-7abc-8def-000000000002'),
            userId: UserId::fromString('01912345-6789-7abc-8def-000000000003'),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: \App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2026-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2026-01-01'),
        );

        $repo = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repo->method('findById')->willReturn($membership);
        $repo->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $handler = new ChangeClinicMembershipRoleHandler($repo, new DomainEventPublisher($eventBus));

        $handler(new ChangeClinicMembershipRole(self::MEMBERSHIP_ID, ClinicMemberRole::MANAGER));

        self::assertSame(ClinicMemberRole::MANAGER, $membership->role());
    }

    public function testThrowsWhenNotFound(): void
    {
        $repo = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $handler = new ChangeClinicMembershipRoleHandler($repo, new DomainEventPublisher($this->createStub(EventBusInterface::class)));

        $this->expectException(\InvalidArgumentException::class);
        $handler(new ChangeClinicMembershipRole(self::MEMBERSHIP_ID, ClinicMemberRole::MANAGER));
    }
}
