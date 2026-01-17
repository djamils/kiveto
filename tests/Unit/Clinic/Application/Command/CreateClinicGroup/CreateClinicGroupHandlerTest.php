<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Application\Command\CreateClinicGroup;

use App\Clinic\Application\Command\CreateClinicGroup\CreateClinicGroup;
use App\Clinic\Application\Command\CreateClinicGroup\CreateClinicGroupHandler;
use App\Clinic\Domain\ClinicGroup;
use App\Clinic\Domain\Repository\ClinicGroupRepositoryInterface;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class CreateClinicGroupHandlerTest extends TestCase
{
    public function testCreateClinicGroupSuccessfully(): void
    {
        $repo = $this->createMock(ClinicGroupRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(ClinicGroup::class))
        ;

        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn('018f1b1e-1234-7890-abcd-0123456789ab');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2024-01-01T12:00:00Z'));

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())
            ->method('publish')
            ->with([], self::isInstanceOf(DomainEventInterface::class))
        ;

        $handler = new CreateClinicGroupHandler(
            $repo,
            $uuidGenerator,
            $clock,
            new DomainEventPublisher($eventBus),
        );

        $groupId = $handler(new CreateClinicGroup(name: 'Test Group'));

        self::assertSame('018f1b1e-1234-7890-abcd-0123456789ab', $groupId);
    }
}
