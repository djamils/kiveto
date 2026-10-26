<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Transport;

use App\System\Notification\Application\Port\OutgoingSms;
use App\System\Notification\Application\Port\SmsSenderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Notifier\Bridge\Brevo\BrevoOptions;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;

/**
 * Sends through the texter transport of NOTIFIER_SMS_DSN directly, to get
 * the provider message id back. The sender is set per message (the Brevo
 * bridge uses it instead of the DSN `sender`); the idempotency key goes as
 * the Brevo `tag`.
 */
final readonly class SymfonyTexterSmsSender implements SmsSenderInterface
{
    public function __construct(
        #[Autowire(service: 'texter.transports')]
        private TransportInterface $transport,
        private ProviderFailureClassifier $failures,
    ) {
    }

    public function send(OutgoingSms $sms): string
    {
        $message = new SmsMessage($sms->to, $sms->text, $sms->from, new BrevoOptions()->tag($sms->tag));

        try {
            $sent = $this->transport->send($message);
        } catch (TransportException $e) {
            throw $this->failures->classify($e, $e->getResponse());
        } catch (TransportExceptionInterface $e) {
            throw $this->failures->classify($e, null);
        } catch (HttpClientException $e) { // @phpstan-ignore catch.neverThrown (the bridge decodes error answers unguarded)
            // An answer the bridge could not decode (e.g. a proxy's HTML page).
            throw $this->failures->classify($e, null);
        }

        // A transport that returns no id (null://) is traced under the tag.
        return $sent?->getMessageId() ?? $sms->tag;
    }
}
