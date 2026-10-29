<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Infrastructure\Messaging;

use App\Context\Clinic\Application\Query\Staff\ListClinicMembersForNotification\ListClinicMembersForNotification;
use App\Context\Clinic\Infrastructure\Adapter\Notification\CommandBusBellNotifier;
use App\Context\Clinic\Infrastructure\Messaging\Consumer\SmsUsageThresholdCrossedConsumer;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\PostInAppNotification\PostInAppNotification;
use App\System\Notification\Domain\Event\SmsUsageThresholdCrossedIntegrationEvent;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SmsUsageThresholdCrossedConsumerTest extends TestCase
{
    private const string CLINIC_ID = '018f1b1e-1234-7890-abcd-0123456789ab';

    /** @var list<object> */
    private array $dispatched = [];

    /**
     * AC 34: one bell entry per threshold, to the administrators with an
     * active account (Clinic's recipients query), keyed by clinic, month and
     * threshold — through Clinic's bell port.
     */
    #[DataProvider('provideTheAdministratorsAreToldCases')]
    public function testTheAdministratorsAreTold(string $threshold, int $used, string $title, string $detail): void
    {
        $queries = new AnsweringQueryBus([ListClinicMembersForNotification::class => ['admin-1', 'admin-2']]);
        $this->consumer($queries)(new SmsUsageThresholdCrossedIntegrationEvent(self::CLINIC_ID, '2026-10', $threshold, $used, 1200, 500));

        $asked = $queries->asked[0] ?? null;
        self::assertInstanceOf(ListClinicMembersForNotification::class, $asked);
        self::assertSame([self::CLINIC_ID, null, true], [$asked->clinicId, $asked->role, $asked->administratorsOnly]);

        self::assertCount(1, $this->dispatched);
        $post = $this->dispatched[0];
        self::assertInstanceOf(PostInAppNotification::class, $post);
        self::assertSame(self::CLINIC_ID, $post->clinicId);
        self::assertSame(['admin-1', 'admin-2'], $post->userIds);
        self::assertSame('SMS_QUOTA', $post->kind);
        self::assertSame($title, $post->title);
        self::assertSame($detail, $post->detail);
        self::assertSame('clinic_settings_notifications', $post->route);
        self::assertSame([], $post->routeParams);
        self::assertSame('sms-quota:' . self::CLINIC_ID . ':2026-10:' . $threshold, $post->idempotencyKey);
    }

    /**
     * @return iterable<string, array{string, int, string, string}>
     */
    public static function provideTheAdministratorsAreToldCases(): iterable
    {
        yield '80 %' => ['80', 960, 'Quota SMS à 80 %', "960 / 1\u{202F}200 segments utilisés ce mois-ci"];
        yield '100 %' => ['100', 1200, 'Quota SMS atteint', "1\u{202F}200 / 1\u{202F}200 segments utilisés ce mois-ci · les SMS suivants sont comptés en dépassement"];
        yield 'cap' => ['CAP', 1700, 'Plafond de dépassement atteint — rappels SMS non critiques basculés sur l’e-mail ou bloqués', 'Dépassement : 500 segments (plafond 500)'];
    }

    public function testAClinicWithoutAdministratorIsToldNothing(): void
    {
        $this->consumer(new AnsweringQueryBus([ListClinicMembersForNotification::class => []]))(new SmsUsageThresholdCrossedIntegrationEvent(self::CLINIC_ID, '2026-10', '80', 960, 1200, 500));

        self::assertSame([], $this->dispatched);
    }

    private function consumer(AnsweringQueryBus $queries): SmsUsageThresholdCrossedConsumer
    {
        $bus = $this->createStub(CommandBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $command): int {
            $this->dispatched[] = $command;

            return 1;
        });

        $bell = new CommandBusBellNotifier($bus);

        return new SmsUsageThresholdCrossedConsumer($queries, $bell);
    }
}
