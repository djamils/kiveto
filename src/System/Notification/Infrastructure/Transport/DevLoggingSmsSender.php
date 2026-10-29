<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Transport;

use App\System\Notification\Application\Port\OutgoingSms;
use App\System\Notification\Application\Port\SmsSenderInterface;
use Psr\Log\LoggerInterface;

/**
 * Development only: writes the recipient and the body of every SMS to the
 * `dev_sms` channel (var/log/dev.log of the worker that sends it), then
 * hands the SMS to the real sender. It is how a Portal one-time code is read
 * in dev, the journal erasing it once sent. Declared under `when@dev` only
 * and excluded from the `App\` auto-registration, so production has neither
 * the service nor the decoration.
 */
final readonly class DevLoggingSmsSender implements SmsSenderInterface
{
    public function __construct(
        private SmsSenderInterface $inner,
        private LoggerInterface $logger,
    ) {
    }

    public function send(OutgoingSms $sms): string
    {
        $this->logger->info('SMS to {to}: {body}', ['to' => $sms->to, 'body' => $sms->text]);

        return $this->inner->send($sms);
    }
}
