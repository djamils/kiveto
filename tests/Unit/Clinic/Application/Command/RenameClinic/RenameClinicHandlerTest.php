<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Application\Command\RenameClinic;

use App\Clinic\Application\Command\RenameClinic\RenameClinic;
use App\Clinic\Application\Command\RenameClinic\RenameClinicHandler;
use App\Clinic\Domain\Clinic;
use App\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Clinic\Domain\ValueObject\ClinicSlug;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class RenameClinicHandlerTest extends TestCase
{
    public function testRenameClinicSuccessfully(): void
    {
        $clinicId = ClinicId::fromString('018f1b1e-1234-7890-abcd-0123456789ab');
        $clinic = Clinic::create(
            $clinicId,
            'Old Name',
            ClinicSlug::fromString('old-name'),
            TimeZone::fromString('Europe/Paris'),
            Locale::fromString('fr-FR'),
            new \DateTimeImmutable('2024-01-01T10:00:00Z'),
            null,
        );

        $repo = $this->createMock(ClinicRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findById')
            ->with(self::callback(static fn (ClinicId $id): bool => $id->equals($clinicId)))
            ->willReturn($clinic)
        ;
        $repo->expects(self::once())
            ->method('save')
            ->with($clinic)
        ;

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2024-01-02T12:00:00Z'));

        $handler = new RenameClinicHandler($repo, $clock);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())
            ->method('publish')
            ->with([], self::isInstanceOf(DomainEventInterface::class))
        ;

        $eventPublisher = new DomainEventPublisher($eventBus);
        $handler->setDomainEventPublisher($eventPublisher);

        $handler(new RenameClinic($clinicId->toString(), 'New Name'));

        self::assertSame('New Name', $clinic->name());
    }

    public function testThrowsExceptionWhenClinicNotFound(): void
    {
        $repo = $this->createMock(ClinicRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findById')
            ->willReturn(null)
        ;

        $clock = $this->createStub(ClockInterface::class);
        $handler = new RenameClinicHandler($repo, $clock);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Clinic with ID');

        $handler(new RenameClinic('018f1b1e-1234-7890-abcd-0123456789ab', 'New Name'));
    }
}
