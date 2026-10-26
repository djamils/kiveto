<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Adapter\Notification;

use App\Context\Billing\Infrastructure\Adapter\Notification\QueryBusSmsSegmentEstimator;
use App\System\Notification\Application\Query\PreviewSmsSegments\PreviewSmsSegments;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

final class QueryBusSmsSegmentEstimatorTest extends TestCase
{
    public function testTheReminderSmsIsEstimatedByNotification(): void
    {
        $bus = new AnsweringQueryBus([PreviewSmsSegments::class => 2]);

        self::assertSame(2, new QueryBusSmsSegmentEstimator($bus)->reminderSegments('clinic-1', ['invoiceNumber' => 'FAC-1']));

        $query = $bus->asked[0];
        self::assertInstanceOf(PreviewSmsSegments::class, $query);
        self::assertSame(['clinic-1', 'INVOICE_REMINDER', ['invoiceNumber' => 'FAC-1'], 1], [$query->clinicId, $query->purpose, $query->parameters, $query->count]);
        self::assertSame(0, new QueryBusSmsSegmentEstimator(new AnsweringQueryBus())->reminderSegments('clinic-1', []));
    }
}
