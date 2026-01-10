<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Application\Command\RenameClinicGroup;

use App\Clinic\Application\Command\RenameClinicGroup\RenameClinicGroup;
use App\Clinic\Application\Command\RenameClinicGroup\RenameClinicGroupHandler;
use App\Clinic\Domain\ClinicGroup;
use App\Clinic\Domain\Repository\ClinicGroupRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Shared\Application\Event\DomainEventPublisher;
use PHPUnit\Framework\TestCase;

final class RenameClinicGroupHandlerTest extends TestCase
{
    public function testRenameClinicGroupSuccessfully(): void
    {
        $groupId = ClinicGroupId::fromString('018f1b1e-1234-7890-abcd-0123456789ab');
        $group   = ClinicGroup::create(
            $groupId,
            'Old Name',
            new \DateTimeImmutable('2024-01-01T10:00:00Z'),
        );

        $repo = $this->createMock(ClinicGroupRepositoryInterface::class);
        $repo->method('findById')->willReturn($group);
        $repo->expects(self::once())->method('save')->with($group);

        $handler = new RenameClinicGroupHandler($repo);
        $handler->setDomainEventPublisher(new DomainEventPublisher($this->createStub(\App\Shared\Application\Bus\EventBusInterface::class)));

        $handler(new RenameClinicGroup($groupId->toString(), 'New Name'));

        self::assertSame('New Name', $group->name());
    }

    public function testThrowsExceptionWhenGroupNotFound(): void
    {
        $repo = $this->createStub(ClinicGroupRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $handler = new RenameClinicGroupHandler($repo);
        $handler->setDomainEventPublisher(new DomainEventPublisher($this->createStub(\App\Shared\Application\Bus\EventBusInterface::class)));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Clinic group with ID "018f1b1e-1234-7890-abcd-0123456789ab" not found.');

        $handler(new RenameClinicGroup('018f1b1e-1234-7890-abcd-0123456789ab', 'New Name'));
    }
}
