<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Infrastructure\Messaging;

use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Infrastructure\Messaging\Consumer\SmsUsageThresholdCrossedConsumer;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Application\Command\PostInAppNotification\PostInAppNotification;
use App\System\Notification\Domain\Event\SmsUsageThresholdCrossedIntegrationEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SmsUsageThresholdCrossedConsumerTest extends TestCase
{
    private const string CLINIC_ID = '018f1b1e-1234-7890-abcd-0123456789ab';

    /** @var list<object> */
    private array $dispatched = [];

    /**
     * AC 34: one bell entry per threshold, to the administrators, keyed by
     * clinic, month and threshold.
     */
    #[DataProvider('provideTheAdministratorsAreToldCases')]
    public function testTheAdministratorsAreTold(string $threshold, int $used, string $title, string $detail): void
    {
        $this->consumer(['admin-1', 'admin-2'])(new SmsUsageThresholdCrossedIntegrationEvent(self::CLINIC_ID, '2026-10', $threshold, $used, 1200, 500));

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
        yield '100 %' => ['100', 1200, 'Quota SMS atteint', "1\u{202F}200 / 1\u{202F}200 segments utilisés ce mois-ci · les SMS suivants sont facturés en dépassement"];
        yield 'cap' => ['CAP', 1700, 'Plafond de dépassement atteint — rappels basculés sur l’e-mail', 'Dépassement : 500 segments (plafond 500)'];
    }

    public function testAClinicWithoutAdministratorIsToldNothing(): void
    {
        $this->consumer([])(new SmsUsageThresholdCrossedIntegrationEvent(self::CLINIC_ID, '2026-10', '80', 960, 1200, 500));

        self::assertSame([], $this->dispatched);
    }

    /**
     * @param list<string> $administrators
     */
    private function consumer(array $administrators): SmsUsageThresholdCrossedConsumer
    {
        $memberships = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $memberships->method('findEffectiveUserIds')->willReturnCallback(
            static function (ClinicId $clinicId, \DateTimeImmutable $now, mixed $role, bool $administratorsOnly) use ($administrators): array {
                self::assertSame(self::CLINIC_ID, $clinicId->toString());
                self::assertNull($role);
                self::assertTrue($administratorsOnly);

                return $administrators;
            },
        );

        $bus = $this->createStub(CommandBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $command): int {
            $this->dispatched[] = $command;

            return 1;
        });

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-26T10:00:00Z'));

        return new SmsUsageThresholdCrossedConsumer($memberships, $bus, $clock);
    }
}
