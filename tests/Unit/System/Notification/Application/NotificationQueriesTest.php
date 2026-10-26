<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Application;

use App\System\Notification\Application\Exception\InvalidNotificationRequestException;
use App\System\Notification\Application\Port\MessageStatusReaderInterface;
use App\System\Notification\Application\Query\GetMessageStatuses\GetMessageStatuses;
use App\System\Notification\Application\Query\GetMessageStatuses\GetMessageStatusesHandler;
use App\System\Notification\Application\Query\GetMessageStatuses\MessageStatusView;
use App\System\Notification\Application\Query\GetSmsUsage\GetSmsUsage;
use App\System\Notification\Application\Query\GetSmsUsage\GetSmsUsageHandler;
use App\System\Notification\Application\Query\PreviewSmsSegments\PreviewSmsSegments;
use App\System\Notification\Application\Query\PreviewSmsSegments\PreviewSmsSegmentsHandler;
use App\System\Notification\Domain\Message\MessagePurpose;
use App\System\Notification\Domain\Quota\SmsQuotaPolicy;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\Notification\Support\FakeRenderer;
use App\Tests\Unit\System\Notification\Support\InMemoryMessages;
use App\Tests\Unit\System\Notification\Support\InMemoryProfiles;
use App\Tests\Unit\System\Notification\Support\InMemoryVeterinarianCounts;
use App\Tests\Unit\System\Notification\Support\Messages;
use PHPUnit\Framework\TestCase;

final class NotificationQueriesTest extends TestCase
{
    /**
     * AC 35: over quota, SMS keep going and the overage is counted at 0.079 € HT.
     */
    public function testTheMonthUsageWithItsOverageAndCap(): void
    {
        $messages = new InMemoryMessages();
        $counts   = new InMemoryVeterinarianCounts();
        $counts->record(Messages::CLINIC, new \DateTimeImmutable('2026-10-01'), 1);
        $sent = Messages::sms(segments: 420, at: new \DateTimeImmutable('2026-10-10 09:00:00'));
        $sent->markSent('b-1', new \DateTimeImmutable('2026-10-10 09:00:01'));
        $messages->save($sent);
        $messages->save(Messages::sms(id: '01960000-0000-7000-8000-000000000502', key: 'k-2', segments: 3));
        $profiles = new InMemoryProfiles();
        $profiles->save(InMemoryProfiles::montmartre(cap: 23));

        $handler = new GetSmsUsageHandler(new SmsQuotaPolicy($messages, $counts, 400, '0.079'), $profiles, new FrozenClock(new \DateTimeImmutable('2026-10-21T09:30:00+02:00')), 500);
        $usage   = $handler(new GetSmsUsage(Messages::CLINIC));

        self::assertSame(['2026-10', 400, 420, 3, 20, 158, 23, 105, true], [$usage->month, $usage->quota, $usage->used, $usage->queued, $usage->overage, $usage->overageAmountCents, $usage->cap, $usage->percent, $usage->capReached]);

        $other = $handler(new GetSmsUsage('01960000-0000-7000-8000-00000000beef'));
        self::assertSame([400, 0, 500, 0, false], [$other->quota, $other->used, $other->cap, $other->percent, $other->capReached], 'no profile: default cap');
    }

    public function testTheSegmentsOfABulkSendArePreviewed(): void
    {
        $profiles = new InMemoryProfiles();
        $profiles->save(InMemoryProfiles::montmartre());
        $handler = new PreviewSmsSegmentsHandler(new FakeRenderer(2), $profiles);
        $params  = ['invoiceNumber' => 'FA-2026-0702', 'shortDate' => '04/09', 'balanceDue' => '64,50 €'];

        self::assertSame(24, $handler(new PreviewSmsSegments(Messages::CLINIC, MessagePurpose::INVOICE_REMINDER->value, $params, 12)));
        self::assertSame(0, $handler(new PreviewSmsSegments(Messages::CLINIC, MessagePurpose::INVOICE_REMINDER->value, $params, -1)));
        self::assertSame(0, $handler(new PreviewSmsSegments('01960000-0000-7000-8000-00000000beef', MessagePurpose::INVOICE_REMINDER->value, $params, 12)), 'no profile: nothing would be sent');
        self::assertSame(2, $handler(new PreviewSmsSegments('01960000-0000-7000-8000-00000000beef', MessagePurpose::TEST->value, ['sentAtLabel' => 'x'])), 'Kiveto writes without a clinic profile');
    }

    public function testAPreviewWithAnUnknownPurposeIsRefused(): void
    {
        $this->expectException(InvalidNotificationRequestException::class);

        new PreviewSmsSegmentsHandler(new FakeRenderer(), new InMemoryProfiles())(new PreviewSmsSegments(Messages::CLINIC, 'NEWSLETTER', []));
    }

    public function testStatusesAreReadForValidIdsOnly(): void
    {
        $reader = new class implements MessageStatusReaderInterface {
            /** @var list<list<string>> */
            public array $asked = [];

            public function statuses(array $messageIds): array
            {
                $this->asked[] = $messageIds;

                return [$messageIds[0] => new MessageStatusView($messageIds[0], 'EMAIL', 'BOUNCED', 'Non délivré', '2026-10-21T09:31:00+00:00')];
            }
        };
        $handler = new GetMessageStatusesHandler($reader);

        self::assertSame([], $handler(new GetMessageStatuses(['nope'])));
        $views = $handler(new GetMessageStatuses([Messages::CLINIC, 'nope', Messages::CLINIC]));

        self::assertSame([[Messages::CLINIC]], $reader->asked);
        self::assertSame('Non délivré', $views[Messages::CLINIC]->statusLabel);
    }
}
