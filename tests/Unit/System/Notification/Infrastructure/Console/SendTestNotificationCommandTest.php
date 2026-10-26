<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Infrastructure\Console;

use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use App\System\Notification\Infrastructure\Console\SendTestNotificationCommand;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\Notification\Support\CapturingBuses;
use App\Tests\Unit\System\Notification\Support\Messages;
use App\Tests\Unit\System\Notification\Support\SequentialUuids;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SendTestNotificationCommandTest extends TestCase
{
    public function testAnAddressRequestsATestEmail(): void
    {
        [$buses, $tester] = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['clinicId' => Messages::CLINIC, 'recipient' => 'djamil@example.org']));

        $request = $buses->commandsOf(RequestNotification::class)[0];
        self::assertSame(['TEST', 'EMAIL', 'djamil@example.org', Messages::CLINIC], [$request->purpose, $request->channel, $request->recipientAddress, $request->clinicId]);
        self::assertSame('test:01960000-0000-7000-8000-000000000001', $request->idempotencyKey);
        self::assertSame(['sentAtLabel' => '26/10/2026 à 10:42'], $request->parameters);
        self::assertStringContainsString('Test message msg-1 requested', $tester->getDisplay());
    }

    public function testANumberRequestsATestSms(): void
    {
        [$buses, $tester] = $this->tester();

        $tester->execute(['clinicId' => Messages::CLINIC, 'recipient' => '+33612345678']);

        self::assertSame('SMS', $buses->commandsOf(RequestNotification::class)[0]->channel);
    }

    public function testAnInvalidClinicIdIsRefused(): void
    {
        [$buses, $tester] = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['clinicId' => 'montmartre', 'recipient' => 'a@b.fr']));
        self::assertSame([], $buses->commands);
    }

    public function testAnInvalidRecipientIsReported(): void
    {
        [$buses, $tester]  = $this->tester();
        $buses->onDispatch = static fn (): never => throw new \InvalidArgumentException('Invalid SMS recipient "0612".');

        self::assertSame(Command::FAILURE, $tester->execute(['clinicId' => Messages::CLINIC, 'recipient' => '0612']));
        self::assertStringContainsString('Invalid SMS recipient', $tester->getDisplay());
    }

    /**
     * @return array{CapturingBuses, CommandTester}
     */
    private function tester(): array
    {
        $buses             = new CapturingBuses();
        $buses->onDispatch = static fn (): string => 'msg-1';

        return [$buses, new CommandTester(new SendTestNotificationCommand($buses, new SequentialUuids(), new FrozenClock(new \DateTimeImmutable('2026-10-26T09:42:00+00:00'))))];
    }
}
