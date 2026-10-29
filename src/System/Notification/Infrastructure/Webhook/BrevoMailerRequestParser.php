<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Bridge\Brevo\RemoteEvent\BrevoPayloadConverter;
use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * Brevo transactional e-mail events (`/webhook/brevo_mailer`). Kiveto's own
 * parser: the Brevo guard (IP ranges + shared secret) instead of the bridge
 * parser's hard-coded IPs and Basic auth, then the bridge's converter.
 * The converted event keeps the raw Brevo payload, whose `event` name the
 * consumer maps (the bridge folds both bounces into one name).
 */
final class BrevoMailerRequestParser extends AbstractRequestParser
{
    public function __construct(
        private readonly BrevoRequestGuard $guard,
        private readonly BrevoPayloadConverter $converter = new BrevoPayloadConverter(),
    ) {
    }

    /**
     * Brevo only needs a 2xx; the event is consumed asynchronously.
     */
    #[\Override]
    public function createSuccessfulResponse(?Request $request = null): Response
    {
        return new Response('', Response::HTTP_OK);
    }

    #[\Override]
    protected function validate(Request $request): void
    {
        $this->guard->check($request);
        parent::validate($request);
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new ChainRequestMatcher([new MethodRequestMatcher('POST'), new IsJsonRequestMatcher()]);
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): AbstractMailerEvent
    {
        $payload = $request->toArray();
        foreach (['event', 'email', 'message-id', 'ts_event'] as $field) {
            if (!isset($payload[$field])) {
                throw new RejectWebhookException(406, 'Payload is malformed.');
            }
        }

        // Brevo names a complaint `spam`, which the bridge converter only knows as `complaint`.
        if ('spam' === $payload['event']) {
            $payload['event'] = 'complaint';
        }

        try {
            return $this->converter->convert($payload);
        } catch (ParseException $e) {
            throw new RejectWebhookException(406, $e->getMessage(), $e);
        }
    }
}
