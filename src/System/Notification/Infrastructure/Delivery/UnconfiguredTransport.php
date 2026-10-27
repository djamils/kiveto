<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Delivery;

use App\System\Notification\Application\Exception\DeliveryRefusedException;
use App\System\Notification\Application\Port\EmailSenderInterface;
use App\System\Notification\Application\Port\OutgoingEmail;
use App\System\Notification\Application\Port\OutgoingSms;
use App\System\Notification\Application\Port\SmsSenderInterface;
use Psr\Log\LoggerInterface;

/**
 * NOTIFICATION_DELIVERY=brevo with a `null://` transport DSN: a configuration
 * error. Every message of that channel is refused — recorded FAILED with a
 * French reason and an error log — instead of being recorded SENT (and, for
 * an SMS, billed) while nothing leaves.
 */
final readonly class UnconfiguredTransport implements EmailSenderInterface, SmsSenderInterface
{
    public const string EMAIL_REASON = 'Transport e-mail non configuré';
    public const string SMS_REASON   = 'Transport SMS non configuré';

    public function __construct(
        private string $reason,
        private string $dsnVariable,
        private LoggerInterface $logger,
    ) {
    }

    public function send(OutgoingEmail|OutgoingSms $message): string
    {
        $this->logger->error('Notification refused: the Brevo delivery has no real transport.', ['variable' => $this->dsnVariable, 'tag' => $message->tag]);

        throw new DeliveryRefusedException($this->reason);
    }
}
