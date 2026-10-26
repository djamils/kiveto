<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Notification;

use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Notification\Application\Query\GetMessageStatuses\GetMessageStatuses;
use App\System\Notification\Application\Query\GetMessageStatuses\MessageStatusView;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\OutboundMessage;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use App\Tests\Unit\System\Notification\Support\Messages;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MessageStatusReaderTest extends KernelTestCase
{
    public function testStatusesOfManyMessagesInOneQuery(): void
    {
        $messages = self::getContainer()->get('test.' . OutboundMessageRepositoryInterface::class);
        \assert($messages instanceof OutboundMessageRepositoryInterface);
        $email = Messages::email();
        $email->markSent('b-1', new \DateTimeImmutable('2026-10-16T08:01:00+00:00'));
        $email->recordStatus(DeliveryStatus::BOUNCED, new \DateTimeImmutable('2026-10-16T10:05:00+02:00'), OutboundMessage::SOURCE_PROVIDER);
        $messages->save($email);
        $messages->save(Messages::sms());

        $bus = self::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);
        $views = $bus->ask(new GetMessageStatuses([$email->id()->toString(), '01960000-0000-7000-8000-000000000501', '01960000-0000-7000-8000-00000000dead']));
        \assert(\is_array($views));

        $ids = array_keys($views);
        sort($ids);
        self::assertSame(['01960000-0000-7000-8000-000000000501', $email->id()->toString()], $ids);
        $bounced = $views[$email->id()->toString()];
        self::assertInstanceOf(MessageStatusView::class, $bounced);
        self::assertSame(['EMAIL', 'BOUNCED', 'Non délivré', '2026-10-16T08:05:00+00:00'], [$bounced->channel, $bounced->status, $bounced->statusLabel, $bounced->statusChangedAt]);
        $queued = $views['01960000-0000-7000-8000-000000000501'];
        self::assertInstanceOf(MessageStatusView::class, $queued);
        self::assertSame(['SMS', 'En cours'], [$queued->channel, $queued->statusLabel]);
    }
}
