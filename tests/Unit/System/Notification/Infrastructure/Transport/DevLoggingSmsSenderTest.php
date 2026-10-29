<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Infrastructure\Transport;

use App\System\Notification\Application\Port\OutgoingSms;
use App\System\Notification\Application\Port\SmsSenderInterface;
use App\System\Notification\Infrastructure\Transport\DevLoggingSmsSender;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DevLoggingSmsSenderTest extends TestCase
{
    public function testLogsTheRecipientAndBodyThenDelegates(): void
    {
        $sms   = new OutgoingSms('Kiveto', '+33612345678', 'Kiveto : votre code est 123456.', 'otp:c-1:1');
        $calls = [];

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->willReturnCallback(static function (string|\Stringable $message, array $context) use (&$calls): void {
                $calls[] = ['log', (string) $message, $context];
            })
        ;

        $inner = $this->createMock(SmsSenderInterface::class);
        $inner->expects(self::once())
            ->method('send')
            ->with(self::identicalTo($sms))
            ->willReturnCallback(static function () use (&$calls): string {
                $calls[] = ['send'];

                return 'provider-1';
            })
        ;

        self::assertSame('provider-1', new DevLoggingSmsSender($inner, $logger)->send($sms));
        self::assertSame([
            ['log', 'SMS to {to}: {body}', ['to' => '+33612345678', 'body' => 'Kiveto : votre code est 123456.']],
            ['send'],
        ], $calls);
    }
}
