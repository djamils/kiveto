<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Transport;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Application\Exception\DeliveryRefusedException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tells a refusal for good from a temporary failure, from the provider's
 * HTTP answer:
 *
 * - 400–499 except 401, 403, 408 and 429 → refused, never retried; the
 *   journal reason stays French (« Refusé par le fournisseur (HTTP 400) »),
 *   the provider's own message goes to the log;
 * - 401 / 403 → our credentials or authorised IPs are wrong (a key rotation,
 *   an IP not allowed yet): a configuration problem, not the message's —
 *   retried, with an error log, so that fixing it in time saves the queue;
 * - 429 → rate limited, with the Retry-After delay when the provider gives one;
 * - 408, 5xx, an unexpected answer or no answer at all → unavailable, retried.
 */
final readonly class ProviderFailureClassifier
{
    public const string SERVICE = 'Brevo';

    public const string REFUSED = 'Refusé par le fournisseur (HTTP %d)';

    public function __construct(
        private ClockInterface $clock,
        #[Autowire(service: 'monolog.logger.external_api')]
        private LoggerInterface $logger,
    ) {
    }

    public function classify(\Throwable $error, ?ResponseInterface $response): DeliveryRefusedException|ExternalServiceUnavailable
    {
        if (null === $response) {
            return ExternalServiceUnavailable::unreachable(self::SERVICE, $error);
        }

        try {
            $status = $response->getStatusCode();
        } catch (HttpClientException) {
            return ExternalServiceUnavailable::unreachable(self::SERVICE, $error);
        }

        if (401 === $status || 403 === $status) {
            $this->logger->error('Brevo refused our credentials: check the API key and the authorised IPs; the message is retried.', ['status' => $status, 'error' => $error->getMessage()]);
        }

        return match (true) {
            429 === $status => ExternalServiceUnavailable::rateLimited(self::SERVICE, $this->retryAfter($response)),
            401 === $status, 403 === $status, 408 === $status, $status < 400, $status >= 500 => ExternalServiceUnavailable::failed(self::SERVICE, $status),
            default => $this->refused($status, $error),
        };
    }

    private function refused(int $status, \Throwable $error): DeliveryRefusedException
    {
        $this->logger->warning('Brevo refused the message.', ['status' => $status, 'error' => $error->getMessage()]);

        return new DeliveryRefusedException(\sprintf(self::REFUSED, $status), $error);
    }

    /**
     * Retry-After is either delta-seconds or an HTTP-date; anything else is ignored.
     */
    private function retryAfter(ResponseInterface $response): ?int
    {
        try {
            $value = mb_trim($response->getHeaders(false)['retry-after'][0] ?? '');
        } catch (HttpClientException) {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        // IMF-fixdate (RFC 9110), always expressed in GMT.
        $date = \DateTimeImmutable::createFromFormat('!D, d M Y H:i:s \G\M\T', $value, new \DateTimeZone('UTC'));

        return false === $date ? null : max(0, $date->getTimestamp() - $this->clock->now()->getTimestamp());
    }
}
