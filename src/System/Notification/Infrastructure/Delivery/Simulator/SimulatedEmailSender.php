<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Delivery\Simulator;

use App\System\Notification\Application\Port\EmailSenderInterface;
use App\System\Notification\Application\Port\OutgoingEmail;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Infrastructure\Transport\SymfonyMailerEmailSender;

/**
 * The e-mail really goes through MAILER_DSN (Mailpit in dev, null:// in
 * tests); only its delivery webhook is faked.
 */
final readonly class SimulatedEmailSender implements EmailSenderInterface
{
    public function __construct(
        private SymfonyMailerEmailSender $mailer,
        private SimulatedDeliveryScheduler $scheduler,
    ) {
    }

    public function send(OutgoingEmail $email): string
    {
        $externalId = $this->mailer->send($email);
        $this->scheduler->schedule(Channel::EMAIL, $externalId, $email->toAddress);

        return $externalId;
    }
}
