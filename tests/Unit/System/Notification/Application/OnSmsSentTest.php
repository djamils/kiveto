<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Application;

use App\System\Notification\Application\EventSubscriber\OnSmsSent;
use App\System\Notification\Domain\Event\OutboundMessageStatusChanged;
use App\System\Notification\Domain\Event\SmsUsageThresholdCrossedIntegrationEvent;
use App\System\Notification\Domain\Quota\SmsQuotaPolicy;
use App\System\Notification\Domain\Quota\SmsThreshold;
use App\System\Notification\Domain\Quota\SmsUsage;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\Notification\Support\CapturingBuses;
use App\Tests\Unit\System\Notification\Support\InMemoryMessages;
use App\Tests\Unit\System\Notification\Support\InMemoryProfiles;
use App\Tests\Unit\System\Notification\Support\InMemorySmsThresholds;
use App\Tests\Unit\System\Notification\Support\InMemoryVeterinarianCounts;
use App\Tests\Unit\System\Notification\Support\Messages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OnSmsSentTest extends TestCase
{
    private InMemoryMessages $messages;
    private InMemoryProfiles $profiles;
    private CapturingBuses $buses;
    private OnSmsSent $subscriber;
    private int $next = 0;

    protected function setUp(): void
    {
        $this->messages   = new InMemoryMessages();
        $this->profiles   = new InMemoryProfiles();
        $this->buses      = new CapturingBuses();
        $this->subscriber = $this->subscriber();
        $this->profiles->save(InMemoryProfiles::montmartre(cap: 50));
    }

    /**
     * AC 34: 80 % then 100 % are announced once each within the month.
     */
    public function testEachThresholdIsAnnouncedOnceAMonth(): void
    {
        $this->send(319);
        self::assertSame([], $this->crossed(), '79 %');

        $this->send(1);
        self::assertSame(['80'], $this->crossed());

        $this->send(10);
        self::assertSame(['80'], $this->crossed(), 'not repeated');

        $this->send(70);
        self::assertSame(['80', '100'], $this->crossed());

        $this->send(50);
        self::assertSame(['80', '100', 'CAP'], $this->crossed());

        $event = $this->buses->eventsOf(SmsUsageThresholdCrossedIntegrationEvent::class)[2];
        self::assertSame([Messages::CLINIC, '2026-10', 'CAP', 450, 400, 50], [$event->clinicId, $event->month, $event->threshold, $event->used, $event->quota, $event->cap]);
        self::assertSame(Messages::CLINIC, $event->aggregateId());
        self::assertSame('CAP', $event->payload()['threshold']);
    }

    public function testOnlyASentClinicSmsCounts(): void
    {
        $this->send(400);
        $this->buses->events = [];

        ($this->subscriber)(self::changed('DELIVERED', 'SMS', Messages::CLINIC));
        ($this->subscriber)(self::changed('SENT', 'EMAIL', Messages::CLINIC));
        ($this->subscriber)(self::changed('SENT', 'SMS', null));

        self::assertSame([], $this->buses->events);
    }

    public function testAClinicWithoutProfileUsesTheDefaultCap(): void
    {
        $this->profiles   = new InMemoryProfiles();
        $this->subscriber = $this->subscriber();
        $this->send(900);

        self::assertSame(['80', '100', 'CAP'], $this->crossed());
    }

    /**
     * @return iterable<string, array{int, int, list<string>}>
     */
    public static function provideThresholdsReachedByAUsageCases(): iterable
    {
        yield 'below' => [319, 50, []];
        yield '80 %' => [320, 50, ['80']];
        yield 'cap 0 reached at the quota' => [400, 0, ['80', '100', 'CAP']];
        yield 'over quota, under cap' => [449, 50, ['80', '100']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideThresholdsReachedByAUsageCases')]
    public function testThresholdsReachedByAUsage(int $used, int $cap, array $expected): void
    {
        self::assertSame($expected, array_map(static fn (SmsThreshold $t): string => $t->value, SmsThreshold::reachedBy(new SmsUsage('2026-10', 400, $used, 0, 0), $cap)));
    }

    private function subscriber(): OnSmsSent
    {
        return new OnSmsSent(
            new SmsQuotaPolicy($this->messages, new InMemoryVeterinarianCounts(), 400, '0.079'),
            $this->profiles,
            new InMemorySmsThresholds(),
            $this->buses->integrationPublisher(),
            new FrozenClock(new \DateTimeImmutable('2026-10-21T09:30:00+02:00')),
            500,
        );
    }

    private function send(int $segments): void
    {
        $sms = Messages::sms(id: \sprintf('01960000-0000-7000-8000-%012d', ++$this->next), key: 'k-' . $this->next, segments: $segments);
        $sms->markSent('b-' . $this->next, new \DateTimeImmutable('2026-10-21 07:00:00'));
        $this->messages->save($sms);

        ($this->subscriber)(self::changed('SENT', 'SMS', Messages::CLINIC));
    }

    /**
     * @return list<string>
     */
    private function crossed(): array
    {
        return array_map(static fn (SmsUsageThresholdCrossedIntegrationEvent $e): string => $e->threshold, $this->buses->eventsOf(SmsUsageThresholdCrossedIntegrationEvent::class));
    }

    private static function changed(string $status, string $channel, ?string $clinicId): OutboundMessageStatusChanged
    {
        return new OutboundMessageStatusChanged('m', $clinicId, null, 'APPOINTMENT_REMINDER', $channel, 'x', 'x', $status, 'x', null, '2026-10-21T07:00:00+00:00', 2);
    }
}
