<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Application\Command\ChangeClinicSlug;

use App\Clinic\Application\Command\ChangeClinicSlug\ChangeClinicSlug;
use App\Clinic\Application\Command\ChangeClinicSlug\ChangeClinicSlugHandler;
use App\Clinic\Application\Exception\DuplicateClinicSlugException;
use App\Clinic\Domain\Clinic;
use App\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Clinic\Domain\ValueObject\ClinicSlug;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class ChangeClinicSlugHandlerTest extends TestCase
{
    public function testChangeSlugSuccessfully(): void
    {
        $clinicId = ClinicId::fromString('018f1b1e-1234-7890-abcd-0123456789ab');
        $clinic   = Clinic::create(
            $clinicId,
            'Test Clinic',
            ClinicSlug::fromString('old-slug'),
            TimeZone::fromString('Europe/Paris'),
            Locale::fromString('fr-FR'),
            new \DateTimeImmutable('2024-01-01T10:00:00Z'),
            null,
        );

        $repo = $this->createMock(ClinicRepositoryInterface::class);
        $repo->method('findById')->willReturn($clinic);
        $repo->method('existsBySlug')->willReturn(false);
        $repo->expects(self::once())->method('save')->with($clinic);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2024-01-02T12:00:00Z'));

        $handler = new ChangeClinicSlugHandler($repo, $clock);
        $handler->setDomainEventPublisher(new DomainEventPublisher($this->createStub(\App\Shared\Application\Bus\EventBusInterface::class)));

        $handler(new ChangeClinicSlug($clinicId->toString(), 'new-slug'));

        self::assertSame('new-slug', $clinic->slug()->toString());
    }

    public function testThrowsExceptionWhenSlugAlreadyExists(): void
    {
        $clinicId = ClinicId::fromString('018f1b1e-2222-7890-abcd-0123456789ab');
        $clinic   = Clinic::create(
            $clinicId,
            'Test Clinic',
            ClinicSlug::fromString('old-slug'),
            TimeZone::fromString('Europe/Paris'),
            Locale::fromString('fr-FR'),
            new \DateTimeImmutable('2024-01-01T10:00:00Z'),
            null,
        );

        $repo = $this->createMock(ClinicRepositoryInterface::class);
        $repo->expects(self::once())->method('findById')->willReturn($clinic);
        $repo->expects(self::once())->method('existsBySlug')->willReturn(true);
        $repo->expects(self::never())->method('save');

        $clock   = $this->createStub(ClockInterface::class);
        $handler = new ChangeClinicSlugHandler($repo, $clock);

        $this->expectException(DuplicateClinicSlugException::class);

        $handler(new ChangeClinicSlug($clinicId->toString(), 'new-slug'));
    }

    public function testThrowsExceptionWhenClinicNotFound(): void
    {
        $repo = $this->createStub(ClinicRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $clock   = $this->createStub(ClockInterface::class);
        $handler = new ChangeClinicSlugHandler($repo, $clock);
        $handler->setDomainEventPublisher(new DomainEventPublisher($this->createStub(\App\Shared\Application\Bus\EventBusInterface::class)));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Clinic with ID');

        $handler(new ChangeClinicSlug('018f1b1e-1234-7890-abcd-0123456789ab', 'new-slug'));
    }
}
