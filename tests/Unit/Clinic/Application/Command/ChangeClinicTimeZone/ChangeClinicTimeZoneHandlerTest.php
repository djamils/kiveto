<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Application\Command\ChangeClinicTimeZone;

use App\Clinic\Application\Command\ChangeClinicTimeZone\ChangeClinicTimeZone;
use App\Clinic\Application\Command\ChangeClinicTimeZone\ChangeClinicTimeZoneHandler;
use App\Clinic\Domain\Clinic;
use App\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Clinic\Domain\ValueObject\ClinicSlug;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class ChangeClinicTimeZoneHandlerTest extends TestCase
{
    public function testChangeTimeZoneSuccessfully(): void
    {
        $clinicId = ClinicId::fromString('018f1b1e-1234-7890-abcd-0123456789ab');
        $clinic = Clinic::create(
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

        $handler = new ChangeClinicTimeZoneHandler($repo, $clock);
        $handler->setDomainEventPublisher(new DomainEventPublisher($this->createStub(\App\Shared\Application\Bus\EventBusInterface::class)));

        $handler(new ChangeClinicTimeZone($clinicId->toString(), 'America/New_York'));

        self::assertSame('America/New_York', $clinic->timeZone()->toString());
    }
}
