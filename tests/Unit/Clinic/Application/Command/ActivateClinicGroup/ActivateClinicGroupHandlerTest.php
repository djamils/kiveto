<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Application\Command\ActivateClinicGroup;

use App\Clinic\Application\Command\ActivateClinicGroup\ActivateClinicGroup;
use App\Clinic\Application\Command\ActivateClinicGroup\ActivateClinicGroupHandler;
use App\Clinic\Domain\ClinicGroup;
use App\Clinic\Domain\Repository\ClinicGroupRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Shared\Application\Event\DomainEventPublisher;
use PHPUnit\Framework\TestCase;

final class ActivateClinicGroupHandlerTest extends TestCase
{
    public function testActivateClinicGroupSuccessfully(): void
    {
        $groupId = ClinicGroupId::fromString('018f1b1e-1234-7890-abcd-0123456789ab');
        $group   = ClinicGroup::create(
            $groupId,
            'Test Group',
            new \DateTimeImmutable('2024-01-01T10:00:00Z'),
        );
        $group->suspend();

        $repo = $this->createMock(ClinicGroupRepositoryInterface::class);
        $repo->method('findById')->willReturn($group);
        $repo->expects(self::once())->method('save')->with($group);

        $handler = new ActivateClinicGroupHandler($repo);
        $handler->setDomainEventPublisher(new DomainEventPublisher($this->createStub(\App\Shared\Application\Bus\EventBusInterface::class)));

        $handler(new ActivateClinicGroup($groupId->toString()));

        self::assertTrue('active' === $group->status()->value);
    }
}
