<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\ExternalApi;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\ExternalApi\ExternalApiResponse;
use App\Shared\Infrastructure\ExternalApi\RetryAfterAwareClient;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(RetryAfterAwareClient::class)]
#[CoversClass(ExternalApiResponse::class)]
final class RetryAfterAwareClientTest extends TestCase
{
    private TestHandler $logs;

    protected function setUp(): void
    {
        $this->logs = new TestHandler();
    }

    public function testA200JsonAnswerIsDecodedAndLoggedAsOk(): void
    {
        $response = $this->client(new MockResponse('{"results":[{"siren":"523456788"}]}', ['http_code' => 200]))
            ->request('GET', '/search?q=calanques', ['query' => ['per_page' => 5]])
        ;

        self::assertSame(200, $response->status);
        self::assertTrue($response->isSuccessful());
        self::assertSame(['results' => [['siren' => '523456788']]], $response->data);

        $record = $this->singleRecord();
        self::assertSame(Level::Info, $record->level);
        self::assertSame([
            'service'     => 'registry',
            'method'      => 'GET',
            'path'        => '/search',
            'status'      => 200,
            'duration_ms' => $record->context['duration_ms'],
            'outcome'     => 'ok',
        ], $record->context);
        self::assertIsInt($record->context['duration_ms']);
    }

    public function testA200NonJsonAnswerHasNoData(): void
    {
        $response = $this->client(new MockResponse('<html>maintenance</html>'))->request('GET', '/search');

        self::assertSame(200, $response->status);
        self::assertNull($response->data);
    }

    public function testAScalarJsonAnswerHasNoData(): void
    {
        $response = $this->client(new MockResponse('"ok"'))->request('GET', '/search');

        self::assertNull($response->data);
    }

    public function testA4xxIsHandedBackToTheAdapter(): void
    {
        $response = $this->client(new MockResponse('{"erreur":"q trop court"}', ['http_code' => 400]))->request('GET', '/search');

        self::assertSame(400, $response->status);
        self::assertFalse($response->isSuccessful());
        self::assertSame(['erreur' => 'q trop court'], $response->data);
        self::assertSame(Level::Info, $this->singleRecord()->level);
        self::assertSame('ok', $this->singleRecord()->context['outcome']);
    }

    public function testA429WithDeltaSecondsIsRateLimited(): void
    {
        $e = $this->unavailable(new MockResponse('', ['http_code' => 429, 'response_headers' => ['Retry-After' => '30']]));

        self::assertTrue($e->isThrottled());
        self::assertSame(30, $e->retryAfter());

        $record = $this->singleRecord();
        self::assertSame(Level::Warning, $record->level);
        self::assertSame('rate_limited', $record->context['outcome']);
        self::assertSame(429, $record->context['status']);
    }

    public function testA429WithAnHttpDateIsRateLimitedUntilThatDate(): void
    {
        $e = $this->unavailable(new MockResponse('', [
            'http_code'        => 429,
            'response_headers' => ['Retry-After' => 'Mon, 19 Oct 2026 10:00:45 GMT'],
        ]));

        self::assertTrue($e->isThrottled());
        self::assertSame(45, $e->retryAfter());
    }

    public function testA429WithAPastHttpDateRetriesNow(): void
    {
        $e = $this->unavailable(new MockResponse('', [
            'http_code'        => 429,
            'response_headers' => ['Retry-After' => 'Mon, 19 Oct 2026 09:00:00 GMT'],
        ]));

        self::assertSame(0, $e->retryAfter());
    }

    public function testA429WithAnUnreadableRetryAfterHasNone(): void
    {
        $e = $this->unavailable(new MockResponse('', ['http_code' => 429, 'response_headers' => ['Retry-After' => 'soon']]));

        self::assertTrue($e->isThrottled());
        self::assertNull($e->retryAfter());
    }

    public function testA429WithoutRetryAfterHasNone(): void
    {
        $e = $this->unavailable(new MockResponse('', ['http_code' => 429]));

        self::assertTrue($e->isThrottled());
        self::assertNull($e->retryAfter());
    }

    public function testA503IsFailedAndNotThrottled(): void
    {
        $e = $this->unavailable(new MockResponse('', ['http_code' => 503, 'response_headers' => ['Retry-After' => '120']]));

        self::assertFalse($e->isThrottled());
        self::assertNull($e->retryAfter());
        self::assertSame('registry failed (HTTP 503).', $e->getMessage());
        self::assertSame('failed', $this->singleRecord()->context['outcome']);
        self::assertSame(Level::Warning, $this->singleRecord()->level);
    }

    public function testA500IsFailed(): void
    {
        $e = $this->unavailable(new MockResponse('boom', ['http_code' => 500]));

        self::assertFalse($e->isThrottled());
        self::assertSame('failed', $this->singleRecord()->context['outcome']);
    }

    public function testATransportErrorIsUnreachable(): void
    {
        $e = $this->unavailable(new MockResponse('', ['error' => 'Could not resolve host']));

        self::assertFalse($e->isThrottled());
        self::assertInstanceOf(TransportException::class, $e->getPrevious());

        $record = $this->singleRecord();
        self::assertSame('unreachable', $record->context['outcome']);
        self::assertNull($record->context['status']);
    }

    public function testATimeoutIsUnreachable(): void
    {
        $client = $this->client(new MockResponse(['', 'late body']));

        try {
            $client->request('GET', '/search', ['timeout' => 0.01]);
            self::fail('A timeout must throw.');
        } catch (ExternalServiceUnavailable $e) {
            self::assertFalse($e->isThrottled());
            self::assertSame('registry could not be reached.', $e->getMessage());
        }

        self::assertSame('unreachable', $this->singleRecord()->context['outcome']);
    }

    public function testNothingIsRetried(): void
    {
        $calls  = 0;
        $client = new RetryAfterAwareClient(
            new MockHttpClient(static function () use (&$calls): MockResponse {
                ++$calls;

                return new MockResponse('', ['http_code' => 503]);
            }),
            'registry',
            new Logger('external_api', [$this->logs]),
            $this->clock(),
        );

        try {
            $client->request('GET', '/search');
        } catch (ExternalServiceUnavailable) {
        }

        self::assertSame(1, $calls);
    }

    public function testNoBodyHeaderNorQueryStringIsLogged(): void
    {
        $this->client(new MockResponse('{"secret":"answer-body"}', ['response_headers' => ['X-Trace' => 'header-value']]))
            ->request('POST', '/check-vat-number?token=query-value', [
                'json'    => ['vatNumber' => 'body-value'],
                'headers' => ['Authorization' => 'Bearer header-secret'],
                'query'   => ['q' => 'option-query'],
            ])
        ;

        $record = $this->singleRecord();
        $dump   = json_encode([$record->message, $record->context, $record->extra], \JSON_THROW_ON_ERROR);

        foreach (['answer-body', 'header-value', 'query-value', 'body-value', 'header-secret', 'option-query', '?'] as $leak) {
            self::assertStringNotContainsString($leak, $dump);
        }

        self::assertSame('/check-vat-number', $record->context['path']);
    }

    private function client(MockResponse $response): RetryAfterAwareClient
    {
        return new RetryAfterAwareClient(
            new MockHttpClient($response, 'https://example.test'),
            'registry',
            new Logger('external_api', [$this->logs]),
            $this->clock(),
        );
    }

    private function unavailable(MockResponse $response): ExternalServiceUnavailable
    {
        try {
            $this->client($response)->request('GET', '/search?q=x');
        } catch (ExternalServiceUnavailable $e) {
            self::assertSame('registry', $e->service());

            return $e;
        }

        self::fail('ExternalServiceUnavailable expected.');
    }

    private function clock(): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-19 10:00:00', new \DateTimeZone('UTC')));

        return $clock;
    }

    private function singleRecord(): LogRecord
    {
        $records = $this->logs->getRecords();
        self::assertCount(1, $records);

        return $records[0];
    }
}
