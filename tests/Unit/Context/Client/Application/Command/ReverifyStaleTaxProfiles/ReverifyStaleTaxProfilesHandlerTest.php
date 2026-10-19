<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Command\ReverifyStaleTaxProfiles;

use App\Context\Client\Application\Command\ReverifyStaleTaxProfiles\ReverifyStaleTaxProfiles;
use App\Context\Client\Application\Command\ReverifyStaleTaxProfiles\ReverifyStaleTaxProfilesHandler;
use App\Context\Client\Application\Command\VerifyClientTaxProfile\VerifyClientTaxProfile;
use App\Context\Client\Application\Verification\TaxProfileVerificationScheduleInterface;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Application\Bus\ExternalApiCommandInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

#[CoversClass(ReverifyStaleTaxProfilesHandler::class)]
final class ReverifyStaleTaxProfilesHandlerTest extends TestCase
{
    public function testEveryMarkedProfileIsQueuedOnTheBatchQueue(): void
    {
        $schedule = $this->createMock(TaxProfileVerificationScheduleInterface::class);
        $schedule->expects(self::once())->method('findStale')
            ->with(
                self::callback(static fn (\DateTimeImmutable $d): bool => '2026-09-19 02:17:00' === $d->format('Y-m-d H:i:s')),
                self::callback(static fn (\DateTimeImmutable $d): bool => '2026-10-18 02:17:00' === $d->format('Y-m-d H:i:s')),
            )
            ->willReturn([
                ['clinicId' => 'clinic-a', 'clientId' => 'client-1'],
                ['clinicId' => 'clinic-b', 'clientId' => 'client-2'],
                ['clinicId' => 'clinic-a', 'clientId' => 'client-3'],
            ])
        ;
        $schedule->expects(self::once())->method('markRequested')
            ->with(
                ['client-1', 'client-2', 'client-3'],
                self::callback(static fn (\DateTimeImmutable $d): bool => '2026-10-19 02:17:00' === $d->format('Y-m-d H:i:s')),
                self::callback(static fn (\DateTimeImmutable $d): bool => '2026-10-18 02:17:00' === $d->format('Y-m-d H:i:s')),
            )
            // client-2 was requested meanwhile by « Revérifier »: not marked here.
            ->willReturn(['client-1', 'client-3'])
        ;

        $sent = [];
        $bus  = $this->createMock(ExternalApiCommandBusInterface::class);
        $bus->expects(self::exactly(2))->method('send')->willReturnCallback(static function (ExternalApiCommandInterface $command, object ...$stamps) use (&$sent): void {
            $sent[] = [$command, $stamps];
        });

        $this->handler($schedule, $bus)(new ReverifyStaleTaxProfiles());

        $targets = [];

        foreach ($sent as [$command, $stamps]) {
            self::assertInstanceOf(VerifyClientTaxProfile::class, $command);
            self::assertCount(1, $stamps);
            self::assertInstanceOf(TransportNamesStamp::class, $stamps[0]);
            self::assertSame(['async_external_batch'], $stamps[0]->getTransportNames());
            $targets[] = $command->clinicId . '/' . $command->clientId;
        }

        self::assertSame(['clinic-a/client-1', 'clinic-a/client-3'], $targets);
    }

    public function testNothingStaleQueuesNothing(): void
    {
        $schedule = $this->createMock(TaxProfileVerificationScheduleInterface::class);
        $schedule->method('findStale')->willReturn([]);
        $schedule->expects(self::never())->method('markRequested');

        $bus = $this->createMock(ExternalApiCommandBusInterface::class);
        $bus->expects(self::never())->method('send');

        $this->handler($schedule, $bus)(new ReverifyStaleTaxProfiles());
    }

    private function handler(TaxProfileVerificationScheduleInterface $schedule, ExternalApiCommandBusInterface $bus): ReverifyStaleTaxProfilesHandler
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-19 02:17:00'));

        return new ReverifyStaleTaxProfilesHandler($schedule, $bus, $clock);
    }
}
