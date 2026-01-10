<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Application\Command\CreateClinic;

use App\Clinic\Application\Command\CreateClinic\CreateClinic;
use App\Clinic\Application\Command\CreateClinic\CreateClinicHandler;
use App\Clinic\Application\Exception\DuplicateClinicSlugException;
use App\Clinic\Domain\Clinic;
use App\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicSlug;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class CreateClinicHandlerTest extends TestCase
{
    public function testCreateClinicSuccessfully(): void
    {
        $repo = $this->createMock(ClinicRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('existsBySlug')
            ->with(self::callback(static fn (ClinicSlug $slug): bool => 'test-clinic' === $slug->toString()))
            ->willReturn(false)
        ;
        $repo->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Clinic::class))
        ;

        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn('018f1b1e-1234-7890-abcd-0123456789ab');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2024-01-01T12:00:00Z'));

        $handler = new CreateClinicHandler($repo, $uuidGenerator, $clock);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())
            ->method('publish')
            ->with([], self::isInstanceOf(DomainEventInterface::class))
        ;

        $eventPublisher = new DomainEventPublisher($eventBus);
        $handler->setDomainEventPublisher($eventPublisher);

        $clinicId = $handler(new CreateClinic(
            name: 'Test Clinic',
            slug: 'test-clinic',
            timeZone: 'Europe/Paris',
            locale: 'fr-FR',
            clinicGroupId: null,
        ));

        self::assertSame('018f1b1e-1234-7890-abcd-0123456789ab', $clinicId);
    }

    public function testThrowsExceptionWhenSlugAlreadyExists(): void
    {
        $repo = $this->createMock(ClinicRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('existsBySlug')
            ->willReturn(true)
        ;
        $repo->expects(self::never())->method('save');

        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn('018f1b1e-1234-7890-abcd-0123456789ab');

        $clock = $this->createStub(ClockInterface::class);

        $handler = new CreateClinicHandler($repo, $uuidGenerator, $clock);

        $this->expectException(DuplicateClinicSlugException::class);

        $handler(new CreateClinic(
            name: 'Test Clinic',
            slug: 'test-clinic',
            timeZone: 'Europe/Paris',
            locale: 'fr-FR',
            clinicGroupId: null,
        ));
    }
}
