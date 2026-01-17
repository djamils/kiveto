<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Application\Command\SuspendClinicGroup;

use App\Clinic\Application\Command\SuspendClinicGroup\SuspendClinicGroup;
use App\Clinic\Application\Command\SuspendClinicGroup\SuspendClinicGroupHandler;
use App\Clinic\Domain\ClinicGroup;
use App\Clinic\Domain\Repository\ClinicGroupRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use PHPUnit\Framework\TestCase;

final class SuspendClinicGroupHandlerTest extends TestCase
{
    public function testSuspendClinicGroupSuccessfully(): void
    {
        $groupId = ClinicGroupId::fromString('018f1b1e-1234-7890-abcd-0123456789ab');
        $group   = ClinicGroup::create(
            $groupId,
            'Test Group',
            new \DateTimeImmutable('2024-01-01T10:00:00Z'),
        );

        $repo = $this->createMock(ClinicGroupRepositoryInterface::class);
        $repo->method('findById')->willReturn($group);
        $repo->expects(self::once())->method('save')->with($group);

        $handler = new SuspendClinicGroupHandler(
            $repo,
            new DomainEventPublisher($this->createStub(EventBusInterface::class)),
        );

        $handler(new SuspendClinicGroup($groupId->toString()));

        self::assertTrue('suspended' === $group->status()->value);
    }

    public function testThrowsExceptionWhenGroupNotFound(): void
    {
        $repo = $this->createStub(ClinicGroupRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $handler = new SuspendClinicGroupHandler(
            $repo,
            new DomainEventPublisher($this->createStub(EventBusInterface::class)),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Clinic group with ID "018f1b1e-1234-7890-abcd-0123456789ab" not found.');

        $handler(new SuspendClinicGroup('018f1b1e-1234-7890-abcd-0123456789ab'));
    }
}
