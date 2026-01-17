<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Application\Command\ChangeClinicStatus;

use App\Clinic\Application\Command\ChangeClinicStatus\ChangeClinicStatus;
use App\Clinic\Application\Command\ChangeClinicStatus\ChangeClinicStatusHandler;
use App\Clinic\Domain\Clinic;
use App\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Clinic\Domain\ValueObject\ClinicSlug;
use App\Clinic\Domain\ValueObject\ClinicStatus;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class ChangeClinicStatusHandlerTest extends TestCase
{
    public function testSuspendClinic(): void
    {
        $clinicId = ClinicId::fromString('018f1b1e-1234-7890-abcd-0123456789ab');
        $clinic   = Clinic::create(
            $clinicId,
            'Test Clinic',
            ClinicSlug::fromString('test-clinic'),
            TimeZone::fromString('Europe/Paris'),
            Locale::fromString('fr-FR'),
            new \DateTimeImmutable('2024-01-01T10:00:00Z'),
            null,
        );

        $repo = $this->createMock(ClinicRepositoryInterface::class);
        $repo->method('findById')->willReturn($clinic);
        $repo->expects(self::once())->method('save')->with($clinic);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2024-01-02T12:00:00Z'));

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $handler = new ChangeClinicStatusHandler(
            $repo,
            $clock,
            new DomainEventPublisher($eventBus),
        );

        $handler(new ChangeClinicStatus($clinicId->toString(), ClinicStatus::SUSPENDED));

        self::assertSame(ClinicStatus::SUSPENDED, $clinic->status());
    }

    public function testCloseClinic(): void
    {
        $clinicId = ClinicId::fromString('018f1b1e-2222-7890-abcd-0123456789ab');
        $clinic   = Clinic::create(
            $clinicId,
            'Test Clinic',
            ClinicSlug::fromString('test-clinic'),
            TimeZone::fromString('Europe/Paris'),
            Locale::fromString('fr-FR'),
            new \DateTimeImmutable('2024-01-01T10:00:00Z'),
            null,
        );

        $repo = $this->createMock(ClinicRepositoryInterface::class);
        $repo->method('findById')->willReturn($clinic);
        $repo->expects(self::once())->method('save');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2024-01-02T12:00:00Z'));

        $handler = new ChangeClinicStatusHandler(
            $repo,
            $clock,
            new DomainEventPublisher($this->createStub(EventBusInterface::class)),
        );

        $handler(new ChangeClinicStatus($clinicId->toString(), ClinicStatus::CLOSED));

        self::assertSame(ClinicStatus::CLOSED, $clinic->status());
    }

    public function testThrowsExceptionWhenClinicNotFound(): void
    {
        $repo = $this->createStub(ClinicRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $clock   = $this->createStub(ClockInterface::class);
        $handler = new ChangeClinicStatusHandler(
            $repo,
            $clock,
            new DomainEventPublisher($this->createStub(EventBusInterface::class)),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Clinic with ID');

        $handler(new ChangeClinicStatus('018f1b1e-1234-7890-abcd-0123456789ab', ClinicStatus::SUSPENDED));
    }
}
