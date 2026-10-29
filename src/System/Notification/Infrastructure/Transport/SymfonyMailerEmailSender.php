<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Transport;

use App\System\Notification\Application\Exception\DeliveryRefusedException;
use App\System\Notification\Application\Port\EmailSenderInterface;
use App\System\Notification\Application\Port\OutgoingEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * Sends through the mailer transport of MAILER_DSN directly (not through the
 * Mailer service, which would queue it again), to get the provider message
 * id back: Brevo's `messageId` with `brevo+api://`, the Message-ID otherwise.
 *
 * The idempotency key goes as a tag (Brevo `tags`, `X-Tag` over SMTP); the
 * clinic logo is embedded inline under its file name, which the HTML
 * references as its content id. An address the MIME layer refuses (sender,
 * reply-to or recipient) is a refusal for good: retrying cannot fix it.
 */
final readonly class SymfonyMailerEmailSender implements EmailSenderInterface
{
    public const string INVALID_ADDRESS = 'Adresse e-mail invalide';

    public function __construct(
        #[Autowire(service: 'mailer.transports')]
        private TransportInterface $transport,
        private ProviderFailureClassifier $failures,
    ) {
    }

    public function send(OutgoingEmail $email): string
    {
        try {
            $message = new Email()
                ->from(new Address($email->fromAddress, $email->fromName))
                ->to(new Address($email->toAddress, $email->toName))
                ->subject($email->subject)
                ->html($email->html)
                ->text($email->text)
            ;

            if (null !== $email->replyTo) {
                $message->replyTo(new Address($email->replyTo));
            }
        } catch (RfcComplianceException $e) {
            throw new DeliveryRefusedException(self::INVALID_ADDRESS, $e);
        }

        $message->getHeaders()->add(new TagHeader($email->tag));

        foreach ($email->attachments as $attachment) {
            $message->attach($attachment->content, $attachment->filename, $attachment->mime);
        }

        if (null !== $email->inlineLogo) {
            $message->embed($email->inlineLogo->content, $email->inlineLogo->filename, $email->inlineLogo->mime);
        }

        try {
            $sent = $this->transport->send($message);
        } catch (HttpTransportException $e) {
            throw $this->failures->classify($e, $e->getResponse());
        } catch (TransportExceptionInterface $e) {
            throw $this->failures->classify($e, null);
        }

        return $sent?->getMessageId() ?? throw new DeliveryRefusedException("Envoi rejeté avant l'expédition");
    }
}
