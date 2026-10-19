<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\ExternalApi;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Shared\Domain\Time\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The one entry point of an adapter to its external service.
 *
 * Wraps the service's scoped client: a 429 becomes a "rate limited" outage
 * (Retry-After parsed), a 5xx a "failed" one, a transport error or a timeout
 * an "unreachable" one; any other answer (2xx, 4xx) is handed back for the
 * adapter to interpret. Never retries. Logs one record per call on the
 * `external_api` channel, without bodies, headers or query strings.
 */
final readonly class RetryAfterAwareClient
{
    public function __construct(
        private HttpClientInterface $client,
        private string $service,
        private LoggerInterface $logger,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $options Symfony HttpClient options
     *
     * @throws ExternalServiceUnavailable
     */
    public function request(string $method, string $path, array $options = []): ExternalApiResponse
    {
        $startedAt = hrtime(true);

        try {
            $response = $this->client->request($method, $path, $options);
            $status   = $response->getStatusCode();

            if (429 === $status) {
                $retryAfter = $this->retryAfter($response);
                $this->log($method, $path, $status, $startedAt, 'rate_limited');

                throw ExternalServiceUnavailable::rateLimited($this->service, $retryAfter);
            }

            if ($status >= 500) {
                $this->log($method, $path, $status, $startedAt, 'failed');

                throw ExternalServiceUnavailable::failed($this->service, $status);
            }

            $data = $this->decode($response->getContent(false));
        } catch (TransportExceptionInterface $e) {
            $this->log($method, $path, null, $startedAt, 'unreachable');

            throw ExternalServiceUnavailable::unreachable($this->service, $e);
        }

        $this->log($method, $path, $status, $startedAt, 'ok');

        return new ExternalApiResponse($status, $data);
    }

    /**
     * @return array<mixed>|null
     */
    private function decode(string $content): ?array
    {
        try {
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($data) ? $data : null;
    }

    /**
     * Retry-After is either delta-seconds or an HTTP-date; anything else is ignored.
     */
    private function retryAfter(ResponseInterface $response): ?int
    {
        $value = trim($response->getHeaders(false)['retry-after'][0] ?? '');

        if ('' === $value) {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        // IMF-fixdate (RFC 9110), always expressed in GMT.
        $date = \DateTimeImmutable::createFromFormat('!D, d M Y H:i:s \G\M\T', $value, new \DateTimeZone('UTC'));

        if (false === $date) {
            return null;
        }

        return max(0, $date->getTimestamp() - $this->clock->now()->getTimestamp());
    }

    private function log(string $method, string $path, ?int $status, int|float $startedAt, string $outcome): void
    {
        $context = [
            'service'     => $this->service,
            'method'      => $method,
            'path'        => explode('?', $path, 2)[0],
            'status'      => $status,
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            'outcome'     => $outcome,
        ];

        if ('ok' === $outcome) {
            $this->logger->info('External API call', $context);

            return;
        }

        $this->logger->warning('External API call unavailable', $context);
    }
}
