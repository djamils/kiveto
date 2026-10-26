<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Infrastructure\Transport;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\System\Notification\Application\Exception\DeliveryRefusedException;
use App\System\Notification\Infrastructure\Transport\ProviderFailureClassifier;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ProviderFailureClassifierTest extends TestCase
{
    private ProviderFailureClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new ProviderFailureClassifier(new FrozenClock(new \DateTimeImmutable('2026-10-21T09:30:00+00:00')));
    }

    /**
     * @return iterable<string, array{int, array<string, string>, ?int}>
     */
    public static function provideTemporaryFailuresAreRetriedCases(): iterable
    {
        yield '429 in seconds' => [429, ['Retry-After' => '60'], 60];
        yield '429 as a date' => [429, ['Retry-After' => 'Wed, 21 Oct 2026 09:32:00 GMT'], 120];
        yield '429 as a past date' => [429, ['Retry-After' => 'Wed, 21 Oct 2026 09:00:00 GMT'], 0];
        yield '429 unreadable' => [429, ['Retry-After' => 'soon'], null];
        yield '429 without delay' => [429, [], null];
        yield '408' => [408, [], null];
        yield '500' => [500, [], null];
        yield '503' => [503, ['Retry-After' => '60'], null];
        yield 'unexpected 200' => [200, [], null];
    }

    /**
     * AC 4: 408, 429 and 5xx are retried, with the provider's delay when it gives one.
     *
     * @param array<string, string> $headers
     */
    #[DataProvider('provideTemporaryFailuresAreRetriedCases')]
    public function testTemporaryFailuresAreRetried(int $status, array $headers, ?int $retryAfter): void
    {
        $failure = $this->classifier->classify(new \RuntimeException('boom'), self::response($status, $headers));

        self::assertInstanceOf(ExternalServiceUnavailable::class, $failure);
        self::assertSame($retryAfter, $failure->retryAfter());
        self::assertSame(429 === $status, $failure->isThrottled());
    }

    /**
     * AC 4: any other 4xx is refused for good, with the reason.
     */
    public function testOtherClientErrorsAreRefusedForGood(): void
    {
        $failure = $this->classifier->classify(new \RuntimeException('Unable to send an email: sender not valid (code 400).'), self::response(400));

        self::assertInstanceOf(DeliveryRefusedException::class, $failure);
        self::assertSame('Refusé par le fournisseur (HTTP 400) : Unable to send an email: sender not valid (code 400).', $failure->reason);

        $long = $this->classifier->classify(new \RuntimeException(str_repeat('x', 500)), self::response(401));
        self::assertInstanceOf(DeliveryRefusedException::class, $long);
        self::assertSame(240, mb_strlen($long->reason));
    }

    public function testNoAnswerIsRetried(): void
    {
        $failure = $this->classifier->classify(new \RuntimeException('timeout'), null);
        self::assertInstanceOf(ExternalServiceUnavailable::class, $failure);
        self::assertFalse($failure->isThrottled());

        $broken = new MockHttpClient(new MockResponse('', ['error' => 'Connection refused']))->request('POST', 'https://api.brevo.com/v3/smtp/email');
        self::assertInstanceOf(ExternalServiceUnavailable::class, $this->classifier->classify(new \RuntimeException('down'), $broken));
    }

    public function testUnreadableHeadersMeanNoDelay(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(429);
        $response->method('getHeaders')->willThrowException(new \Symfony\Component\HttpClient\Exception\TransportException('gone'));

        $failure = $this->classifier->classify(new \RuntimeException('429'), $response);
        self::assertInstanceOf(ExternalServiceUnavailable::class, $failure);
        self::assertNull($failure->retryAfter());
    }

    /**
     * @param array<string, string> $headers
     */
    private static function response(int $status, array $headers = []): ResponseInterface
    {
        $response = new MockHttpClient(new MockResponse('{}', ['http_code' => $status, 'response_headers' => $headers]))->request('POST', 'https://api.brevo.com/v3/smtp/email');
        $response->getStatusCode();

        return $response;
    }
}
