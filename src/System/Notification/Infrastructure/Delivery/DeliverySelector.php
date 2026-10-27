<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Delivery;

use App\System\Notification\Application\Port\EmailSenderInterface;
use App\System\Notification\Application\Port\SmsSenderInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Picks the delivery adapters named by NOTIFICATION_DELIVERY when a sender
 * is first used — at runtime, so a value set after the image was built is
 * still checked: the local simulator never runs in production.
 *
 * - disabled: nothing leaves, every message is recorded FAILED « Envoi désactivé »;
 * - simulator: e-mail through MAILER_DSN (Mailpit in dev), SMS kept local,
 *   delivery webhooks faked (AdvanceSimulatedDelivery);
 * - brevo: the real Brevo transports, real webhooks. A channel whose DSN
 *   (MAILER_DSN, NOTIFIER_SMS_DSN) is still `null://` refuses every message
 *   (UnconfiguredTransport): the null transports accept and drop.
 */
final readonly class DeliverySelector
{
    public const string DISABLED  = 'disabled';
    public const string SIMULATOR = 'simulator';
    public const string BREVO     = 'brevo';

    public function __construct(
        private ContainerInterface $candidates,
        private string $delivery,
        private string $environment,
        private string $mailerDsn,
        private string $smsDsn,
        private LoggerInterface $logger,
    ) {
    }

    public function emailSender(): EmailSenderInterface
    {
        if ($this->unconfigured($this->mailerDsn)) {
            return new UnconfiguredTransport(UnconfiguredTransport::EMAIL_REASON, 'MAILER_DSN', $this->logger);
        }

        $adapter = $this->adapter('email');
        \assert($adapter instanceof EmailSenderInterface);

        return $adapter;
    }

    public function smsSender(): SmsSenderInterface
    {
        if ($this->unconfigured($this->smsDsn)) {
            return new UnconfiguredTransport(UnconfiguredTransport::SMS_REASON, 'NOTIFIER_SMS_DSN', $this->logger);
        }

        $adapter = $this->adapter('sms');
        \assert($adapter instanceof SmsSenderInterface);

        return $adapter;
    }

    private function unconfigured(string $dsn): bool
    {
        return self::BREVO === $this->delivery && str_starts_with(mb_strtolower(mb_trim($dsn)), 'null://');
    }

    private function adapter(string $channel): object
    {
        if (self::SIMULATOR === $this->delivery && 'prod' === $this->environment) {
            throw new \LogicException('The notification delivery simulator cannot run in production.');
        }

        $id = $this->delivery . '.' . $channel;

        if (!$this->candidates->has($id)) {
            throw new \LogicException(\sprintf('Unknown notification delivery "%s".', $this->delivery));
        }

        $adapter = $this->candidates->get($id);
        \assert(\is_object($adapter));

        return $adapter;
    }
}
