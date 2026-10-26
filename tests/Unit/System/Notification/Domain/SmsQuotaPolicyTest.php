<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Domain;

use App\System\Notification\Domain\Quota\SmsQuotaPolicy;
use App\System\Notification\Domain\Quota\SmsUsage;
use App\Tests\Unit\System\Notification\Support\InMemoryMessages;
use App\Tests\Unit\System\Notification\Support\InMemoryVeterinarianCounts;
use App\Tests\Unit\System\Notification\Support\Messages;
use PHPUnit\Framework\TestCase;

final class SmsQuotaPolicyTest extends TestCase
{
    private const string CLINIC = Messages::CLINIC;

    private InMemoryMessages $messages;
    private InMemoryVeterinarianCounts $counts;
    private SmsQuotaPolicy $policy;

    protected function setUp(): void
    {
        $this->messages = new InMemoryMessages();
        $this->counts   = new InMemoryVeterinarianCounts();
        $this->policy   = new SmsQuotaPolicy($this->messages, $this->counts, 400, '0.079');
    }

    /**
     * AC 33: the highest count of the month makes the quota, even when a
     * veterinarian left before the end of the month.
     */
    public function testTheQuotaFollowsTheHighestVeterinarianCountOfTheMonth(): void
    {
        $this->vets('2026-09-30', 5);
        $this->vets('2026-10-01', 2);
        $this->vets('2026-10-12', 3);
        $this->vets('2026-10-30', 2);
        $this->vets('2026-11-01', 6);

        $usage = $this->policy->usage(self::CLINIC, new \DateTimeImmutable('2026-10-31T22:00:00+00:00'));

        self::assertSame('2026-10', $usage->month);
        self::assertSame(1200, $usage->quota);
    }

    public function testAMonthWithoutCountUsesTheLastKnownOne(): void
    {
        $this->vets('2026-10-12', 3);
        $this->vets('2026-10-20', 2);

        self::assertSame(800, $this->policy->usage(self::CLINIC, new \DateTimeImmutable('2026-11-15 10:00:00'))->quota);
    }

    public function testTheQuotaIsNeverBelowOneVeterinarian(): void
    {
        self::assertSame(400, $this->policy->usage(self::CLINIC, new \DateTimeImmutable('2026-11-15 10:00:00'))->quota);

        $this->vets('2026-11-02', 0);
        self::assertSame(400, $this->policy->usage(self::CLINIC, new \DateTimeImmutable('2026-11-15 10:00:00'))->quota);
    }

    /**
     * AC 37: the month is a Europe/Paris month; usage restarts on the 1st.
     */
    public function testUsageRestartsOnTheFirstOfTheMonthInParis(): void
    {
        $this->sent('a', 2, '2026-10-31T23:30:00+01:00');
        $this->sent('b', 5, '2026-11-01T00:10:00+01:00');
        $this->messages->save(Messages::sms(id: '01960000-0000-7000-8000-000000000c01', key: 'q', segments: 3));

        $october = $this->policy->usage(self::CLINIC, new \DateTimeImmutable('2026-10-31T22:59:00+00:00'));
        self::assertSame(['2026-10', 2, 3], [$october->month, $october->used, $october->queued]);

        $november = $this->policy->usage(self::CLINIC, new \DateTimeImmutable('2026-10-31T23:00:00+00:00'));
        self::assertSame(['2026-11', 5], [$november->month, $november->used]);
    }

    /**
     * AC 35: over quota, the overage is priced at 0.079 € HT per segment.
     */
    public function testTheOverageIsPriced(): void
    {
        $this->sent('a', 500, '2026-10-10T10:00:00+02:00');

        $usage = $this->policy->usage(self::CLINIC, new \DateTimeImmutable('2026-10-20 10:00:00'));

        self::assertSame(100, $usage->overage());
        self::assertSame(790, $usage->overageAmountCents);
        self::assertSame(125, $usage->percent());
    }

    public function testTheOverageAmountIsRoundedHalfUp(): void
    {
        $policy = new SmsQuotaPolicy($this->messages, $this->counts, 1, '0.075');
        $this->sent('a', 3, '2026-10-10T10:00:00+02:00');

        // 2 segments × 0.075 € = 0.15 €; 1 more would be 0.225 € → 0.23 €.
        self::assertSame(15, $policy->usage(self::CLINIC, new \DateTimeImmutable('2026-10-20'))->overageAmountCents);
        $this->sent('b', 1, '2026-10-11T10:00:00+02:00');
        self::assertSame(23, $policy->usage(self::CLINIC, new \DateTimeImmutable('2026-10-20'))->overageAmountCents);
        self::assertSame(300, new SmsQuotaPolicy($this->messages, $this->counts, 1, '1')->usage(self::CLINIC, new \DateTimeImmutable('2026-10-20'))->overageAmountCents, 'a whole euro price');
    }

    public function testTheCapCountsSentAndQueuedSegments(): void
    {
        $usage = new SmsUsage('2026-10', 400, 380, 20, 0);

        self::assertFalse($usage->wouldReachCap(10, 10));
        self::assertTrue($usage->wouldReachCap(11, 10));
        self::assertFalse($usage->capReached(10));
        self::assertTrue(new SmsUsage('2026-10', 400, 400, 10, 0)->capReached(10));
        self::assertSame(0, $usage->overage());
        self::assertSame(95, $usage->percent());
    }

    public function testTheParametersAreValidated(): void
    {
        foreach ([[0, '0.079'], [400, '0,079'], [400, '-1']] as [$segments, $price]) {
            try {
                new SmsQuotaPolicy($this->messages, $this->counts, $segments, $price);
                self::fail('refused expected');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTheMonthBoundariesAreParisMidnights(): void
    {
        [$start, $end] = SmsQuotaPolicy::month(new \DateTimeImmutable('2026-03-29T01:30:00+00:00'));

        self::assertSame('2026-03-01T00:00:00+01:00', $start->format(\DateTimeInterface::ATOM));
        self::assertSame('2026-04-01T00:00:00+02:00', $end->format(\DateTimeInterface::ATOM));
    }

    private function vets(string $day, int $count): void
    {
        $this->counts->record(self::CLINIC, new \DateTimeImmutable($day), $count);
    }

    private function sent(string $suffix, int $segments, string $at): void
    {
        $id      = \sprintf('01960000-0000-7000-8000-%012s', bin2hex($suffix));
        $message = Messages::sms(id: $id, key: 'sent-' . $suffix, segments: $segments);
        $message->markSent('ext-' . $suffix, new \DateTimeImmutable($at));
        $this->messages->save($message);
    }
}
