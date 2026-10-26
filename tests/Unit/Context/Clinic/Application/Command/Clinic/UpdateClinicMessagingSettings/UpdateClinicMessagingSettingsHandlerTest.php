<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Clinic\UpdateClinicMessagingSettings;

use App\Context\Clinic\Application\Command\Clinic\UpdateClinicMessagingSettings\UpdateClinicMessagingSettings;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicMessagingSettings\UpdateClinicMessagingSettingsHandler;
use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Event\ClinicMessagingSettingsChanged;
use App\Context\Clinic\Domain\Exception\InvalidMessagingSettingsException;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use PHPUnit\Framework\TestCase;

final class UpdateClinicMessagingSettingsHandlerTest extends TestCase
{
    private const string CLINIC_ID = '018f1b1e-1234-7890-abcd-0123456789ab';

    public function testTheSettingsAreChangedSavedAndAnnounced(): void
    {
        $clinic = self::clinic();
        self::assertNotEmpty($clinic->pullDomainEvents());

        $repository = $this->createMock(ClinicRepositoryInterface::class);
        $repository->method('findById')->willReturn($clinic);
        $repository->expects(self::once())->method('save')->with($clinic);

        $published = [];
        $bus       = $this->createStub(EventBusInterface::class);
        $bus->method('publish')->willReturnCallback(static function (array $stamps, object ...$events) use (&$published): void {
            $published = [...$published, ...$events];
        });

        $handler = new UpdateClinicMessagingSettingsHandler($repository, $this->clock(), new DomainEventPublisher($bus));
        $handler(new UpdateClinicMessagingSettings(self::CLINIC_ID, 'Lilas Vet', 800));

        self::assertSame('Lilas Vet', $clinic->messagingSettings()->smsSender);
        self::assertSame(800, $clinic->messagingSettings()->overageCapSegments);
        self::assertCount(1, $published);
        self::assertInstanceOf(ClinicMessagingSettingsChanged::class, $published[0]);
    }

    public function testAnInvalidSenderIsRefusedBeforeAnySave(): void
    {
        $repository = $this->createMock(ClinicRepositoryInterface::class);
        $repository->method('findById')->willReturn(self::clinic());
        $repository->expects(self::never())->method('save');

        $this->expectException(InvalidMessagingSettingsException::class);

        new UpdateClinicMessagingSettingsHandler($repository, $this->clock(), new DomainEventPublisher($this->createStub(EventBusInterface::class)))(
            new UpdateClinicMessagingSettings(self::CLINIC_ID, 'Clinique des Lilas', 500),
        );
    }

    public function testAnUnknownClinicIsRefused(): void
    {
        $repository = $this->createStub(ClinicRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $this->expectException(\RuntimeException::class);

        new UpdateClinicMessagingSettingsHandler($repository, $this->clock(), new DomainEventPublisher($this->createStub(EventBusInterface::class)))(
            new UpdateClinicMessagingSettings(self::CLINIC_ID, 'Lilas Vet', 500),
        );
    }

    private function clock(): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-15T10:00:00Z'));

        return $clock;
    }

    private static function clinic(): Clinic
    {
        return Clinic::create(
            ClinicId::fromString(self::CLINIC_ID),
            'Clinique des Lilas',
            ClinicSlug::fromString('clinique-des-lilas'),
            TimeZone::fromString('Europe/Paris'),
            Locale::fromString('fr-FR'),
            CountryCode::fromString('FR'),
            CurrencyCode::fromString('EUR'),
            new \DateTimeImmutable('2026-01-01T10:00:00Z'),
        );
    }
}
