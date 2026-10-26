<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Transport;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Application\Exception\DeliveryRefusedException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tells a refusal for good from a temporary failure, from the provider's
 * HTTP answer:
 *
 * - 400–499 except 408 and 429 → refused, never retried;
 * - 429 → rate limited, with the Retry-After delay when the provider gives one;
 * - 408, 5xx, an unexpected answer or no answer at all → unavailable, retried.
 */
final readonly class ProviderFailureClassifier
{
    public const string SERVICE = 'Brevo';

    private const int REASON_MAX_LENGTH = 240;

    public function __construct(private ClockInterface $clock)
    {
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

        return match (true) {
            429 === $status => ExternalServiceUnavailable::rateLimited(self::SERVICE, $this->retryAfter($response)),
            408 === $status, $status < 400, $status >= 500 => ExternalServiceUnavailable::failed(self::SERVICE, $status),
            default => new DeliveryRefusedException(mb_substr(\sprintf('Refusé par le fournisseur (HTTP %d) : %s', $status, $error->getMessage()), 0, self::REASON_MAX_LENGTH), $error),
        };
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
