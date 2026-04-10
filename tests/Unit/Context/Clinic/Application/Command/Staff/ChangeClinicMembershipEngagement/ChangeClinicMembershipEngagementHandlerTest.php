<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipEngagement;

use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipEngagement\ChangeClinicMembershipEngagement;
use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipEngagement\ChangeClinicMembershipEngagementHandler; // phpcs:ignore Generic.Files.LineLength.TooLong
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

final class ChangeClinicMembershipEngagementHandlerTest extends TestCase
{
    private const string MEMBERSHIP_ID = '01912345-6789-7abc-8def-000000000001';

    public function testChangesEngagementSuccessfully(): void
    {
        $membership = $this->buildMembership();

        $repo = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repo->method('findById')->willReturn($membership);
        $repo->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $handler = new ChangeClinicMembershipEngagementHandler($repo, new DomainEventPublisher($eventBus));
        $handler(new ChangeClinicMembershipEngagement(self::MEMBERSHIP_ID, ClinicMembershipEngagement::CONTRACTOR));

        self::assertSame(ClinicMembershipEngagement::CONTRACTOR, $membership->engagement());
    }

    public function testThrowsWhenNotFound(): void
    {
        $repo = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $publisher = new DomainEventPublisher($this->createStub(EventBusInterface::class));
        $handler   = new ChangeClinicMembershipEngagementHandler($repo, $publisher);

        $this->expectException(\InvalidArgumentException::class);
        $handler(new ChangeClinicMembershipEngagement(self::MEMBERSHIP_ID, ClinicMembershipEngagement::CONTRACTOR));
    }

    private function buildMembership(): ClinicMembership
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
        );
    }
}
