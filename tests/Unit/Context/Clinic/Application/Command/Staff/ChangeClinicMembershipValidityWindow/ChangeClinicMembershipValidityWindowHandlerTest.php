<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipValidityWindow;

use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipValidityWindow\ChangeClinicMembershipValidityWindow;
use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipValidityWindow\ChangeClinicMembershipValidityWindowHandler;
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

final class ChangeClinicMembershipValidityWindowHandlerTest extends TestCase
{
    private const string MEMBERSHIP_ID = '01912345-6789-7abc-8def-000000000001';

    public function testChangesValidityWindowSuccessfully(): void
    {
        $membership = $this->buildMembership();

        $repo = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repo->method('findById')->willReturn($membership);
        $repo->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $handler = new ChangeClinicMembershipValidityWindowHandler($repo, new DomainEventPublisher($eventBus));

        $newFrom  = new \DateTimeImmutable('2026-06-01');
        $newUntil = new \DateTimeImmutable('2026-12-31');
        $handler(new ChangeClinicMembershipValidityWindow(self::MEMBERSHIP_ID, $newFrom, $newUntil));

        self::assertSame($newFrom, $membership->validFrom());
        self::assertSame($newUntil, $membership->validUntil());
    }

    public function testThrowsWhenNotFound(): void
    {
        $repo = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $handler = new ChangeClinicMembershipValidityWindowHandler($repo, new DomainEventPublisher($this->createStub(EventBusInterface::class)));

        $this->expectException(\InvalidArgumentException::class);
        $handler(new ChangeClinicMembershipValidityWindow(self::MEMBERSHIP_ID, new \DateTimeImmutable(), null));
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
