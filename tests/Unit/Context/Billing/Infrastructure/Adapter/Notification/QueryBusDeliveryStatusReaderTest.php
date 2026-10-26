<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Adapter\Notification;

use App\Context\Billing\Infrastructure\Adapter\Notification\QueryBusDeliveryStatusReader;
use App\System\Notification\Application\Query\GetMessageStatuses\GetMessageStatuses;
use App\System\Notification\Application\Query\GetMessageStatuses\MessageStatusView;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

final class QueryBusDeliveryStatusReaderTest extends TestCase
{
    public function testTheStatusesComeFromNotification(): void
    {
        $bus = new AnsweringQueryBus([GetMessageStatuses::class => [
            'm-1' => new MessageStatusView('m-1', 'EMAIL', 'BOUNCED', 'Non délivré', '2026-10-26T10:00:00+00:00'),
            'x'   => 'not a view',
        ]]);

        $statuses = new QueryBusDeliveryStatusReader($bus)->statuses(['m-1', 'm-2']);

        self::assertSame(['m-1'], array_keys($statuses));
        self::assertSame(['EMAIL', 'BOUNCED', 'Non délivré'], [$statuses['m-1']->channel, $statuses['m-1']->status, $statuses['m-1']->label]);
        $query = $bus->asked[0];
        self::assertInstanceOf(GetMessageStatuses::class, $query);
        self::assertSame(['m-1', 'm-2'], $query->messageIds);
        self::assertSame([], new QueryBusDeliveryStatusReader(new AnsweringQueryBus())->statuses(['m-1']));
    }
}
