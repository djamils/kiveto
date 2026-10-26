<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Infrastructure\Delivery;

use App\System\Notification\Application\Command\RecordDeliveryEvent\RecordDeliveryEvent;
use App\System\Notification\Application\Exception\DeliveryRefusedException;
use App\System\Notification\Application\Port\OutgoingEmail;
use App\System\Notification\Application\Port\OutgoingSms;
use App\System\Notification\Infrastructure\Delivery\DeliverySelector;
use App\System\Notification\Infrastructure\Delivery\DisabledDelivery;
use App\System\Notification\Infrastructure\Delivery\Simulator\AdvanceSimulatedDelivery;
use App\System\Notification\Infrastructure\Delivery\Simulator\AdvanceSimulatedDeliveryHandler;
use App\System\Notification\Infrastructure\Delivery\Simulator\SimulatedDeliveryScheduler;
use App\System\Notification\Infrastructure\Delivery\Simulator\SimulatedEmailSender;
use App\System\Notification\Infrastructure\Delivery\Simulator\SimulatedSmsSender;
use App\System\Notification\Infrastructure\Transport\ProviderFailureClassifier;
use App\System\Notification\Infrastructure\Transport\SymfonyMailerEmailSender;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\Notification\Support\CapturingBuses;
use App\Tests\Unit\System\Notification\Support\CapturingMessageBus;
use App\Tests\Unit\System\Notification\Support\FakeSenders;
use App\Tests\Unit\System\Notification\Support\SequentialUuids;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class DeliveryAdaptersTest extends TestCase
{
    public function testTheSelectorPicksTheAdaptersOfTheConfiguredDelivery(): void
    {
        $email = new FakeSenders();
        $sms   = new FakeSenders();

        $selector = new DeliverySelector(self::candidates(['brevo.email' => $email, 'brevo.sms' => $sms]), 'brevo', 'prod');

        self::assertSame($email, $selector->emailSender());
        self::assertSame($sms, $selector->smsSender());
    }

    /**
     * AC 5: the simulator is refused in production, when a message is dispatched.
     */
    public function testTheSimulatorIsRefusedInProduction(): void
    {
        $selector = new DeliverySelector(self::candidates(['simulator.email' => new FakeSenders()]), 'simulator', 'prod');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('simulator cannot run in production');

        $selector->emailSender();
    }

    public function testAnUnknownDeliveryIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unknown notification delivery "smtp"');

        new DeliverySelector(self::candidates([]), 'smtp', 'dev')->smsSender();
    }

    public function testDisabledDeliveryRefusesEverything(): void
    {
        $disabled = new DisabledDelivery();

        try {
            $disabled->send(new OutgoingSms('Kiveto', '+33612345678', 'x', 'k'));
            self::fail('Refused.');
        } catch (DeliveryRefusedException $e) {
            self::assertSame('Envoi désactivé', $e->reason);
        }
    }

    public function testTheSimulatedEmailIsReallySentThenItsDeliveryScheduled(): void
    {
        $bus    = self::bus();
        $sender = new SimulatedEmailSender(
            new SymfonyMailerEmailSender(new NullTransport(), new ProviderFailureClassifier(new FrozenClock(new \DateTimeImmutable()))),
            new SimulatedDeliveryScheduler($bus, 5000),
        );

        $id = $sender->send(new OutgoingEmail('Kiveto', 'no-reply@kiveto.fr', null, 'bounce@example.com', '', 'Sujet', '<p>x</p>', 'x', 'test:1'));

        self::assertCount(1, $bus->dispatched);
        [$command, $stamps] = $bus->dispatched[0];
        self::assertInstanceOf(AdvanceSimulatedDelivery::class, $command);
        self::assertSame(['EMAIL', $id, 'bounce@example.com'], [$command->channel, $command->externalId, $command->recipient]);
        self::assertInstanceOf(DelayStamp::class, $stamps[0]);
        self::assertSame(5000, $stamps[0]->getDelay());
    }

    public function testTheSimulatedSmsNeverLeavesTheMachine(): void
    {
        $bus    = self::bus();
        $logger = new BufferingLogger();
        $sender = new SimulatedSmsSender(new SimulatedDeliveryScheduler($bus, 0), new SequentialUuids(), $logger);

        $id = $sender->send(new OutgoingSms('Montmartre', '+33612340000', 'Bonjour', 'appointment:1:reminder'));

        self::assertSame('sim-sms-01960000-0000-7000-8000-000000000001', $id);
        $logs = $logger->cleanLogs();
        self::assertIsArray($logs[0]);
        self::assertSame('Simulated SMS.', $logs[0][1]);
        self::assertIsArray($logs[0][2]);
        self::assertSame('Bonjour', $logs[0][2]['text']);
        $command = $bus->dispatched[0][0];
        self::assertInstanceOf(AdvanceSimulatedDelivery::class, $command);
        self::assertSame(['SMS', $id, '+33612340000'], [$command->channel, $command->externalId, $command->recipient]);
    }

    /**
     * @return iterable<string, array{string, string, string, ?string}>
     */
    public static function provideTheSimulatorPlaysTheProviderWebhookCases(): iterable
    {
        yield 'delivered e-mail' => ['EMAIL', 't.lefebvre@orange.fr', 'DELIVERED', null];
        yield 'bounced e-mail' => ['EMAIL', 'bounce@example.com', 'BOUNCED', 'Adresse inexistante (simulation)'];
        yield 'delivered SMS' => ['SMS', '+33612345678', 'DELIVERED', null];
        yield 'rejected SMS' => ['SMS', '+33612340000', 'REJECTED', 'Numéro refusé (simulation)'];
        yield 'an e-mail ending in 0000 is delivered' => ['EMAIL', 'a0000', 'DELIVERED', null];
    }

    #[DataProvider('provideTheSimulatorPlaysTheProviderWebhookCases')]
    public function testTheSimulatorPlaysTheProviderWebhook(string $channel, string $recipient, string $kind, ?string $reason): void
    {
        $buses = new CapturingBuses();
        new AdvanceSimulatedDeliveryHandler($buses, new FrozenClock(new \DateTimeImmutable('2026-10-21T09:31:00+00:00')))(new AdvanceSimulatedDelivery($channel, 'ext-1', $recipient));

        $event = $buses->commandsOf(RecordDeliveryEvent::class)[0];
        self::assertSame([$channel, 'ext-1', $kind, '2026-10-21T09:31:00+00:00', $reason], [$event->channel, $event->externalId, $event->kind, $event->occurredAt, $event->reason]);
    }

    /**
     * @param array<string, object> $services
     *
     * @return ServiceLocator<mixed>
     */
    private static function candidates(array $services): ServiceLocator
    {
        return new ServiceLocator(array_map(static fn (object $service): \Closure => static fn (): object => $service, $services));
    }

    private static function bus(): CapturingMessageBus
    {
        return new CapturingMessageBus();
    }
}
