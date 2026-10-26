<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Delivery;

use App\System\Notification\Application\Port\EmailSenderInterface;
use App\System\Notification\Application\Port\SmsSenderInterface;
use Psr\Container\ContainerInterface;

/**
 * Picks the delivery adapters named by NOTIFICATION_DELIVERY when a sender
 * is first used — at runtime, so a value set after the image was built is
 * still checked: the local simulator never runs in production.
 *
 * - disabled: nothing leaves, every message is recorded FAILED « Envoi désactivé »;
 * - simulator: e-mail through MAILER_DSN (Mailpit in dev), SMS kept local,
 *   delivery webhooks faked (AdvanceSimulatedDelivery);
 * - brevo: the real Brevo transports, real webhooks.
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
    ) {
    }

    public function emailSender(): EmailSenderInterface
    {
        $adapter = $this->adapter('email');
        \assert($adapter instanceof EmailSenderInterface);

        return $adapter;
    }

    public function smsSender(): SmsSenderInterface
    {
        $adapter = $this->adapter('sms');
        \assert($adapter instanceof SmsSenderInterface);

        return $adapter;
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
