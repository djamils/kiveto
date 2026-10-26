<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Webhook;

use Symfony\Component\HttpFoundation\ChainRequestMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * Brevo transactional SMS events (`/webhook/brevo_sms`): the Brevo guard
 * (IP ranges + shared secret), then `BrevoSmsPayloadConverter`.
 */
final class BrevoSmsRequestParser extends AbstractRequestParser
{
    public function __construct(
        private readonly BrevoRequestGuard $guard,
        private readonly BrevoSmsPayloadConverter $converter,
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

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): SmsEvent
    {
        try {
            return $this->converter->convert($request->toArray());
        } catch (ParseException $e) {
            throw new RejectWebhookException(406, $e->getMessage(), $e);
        }
    }
}
