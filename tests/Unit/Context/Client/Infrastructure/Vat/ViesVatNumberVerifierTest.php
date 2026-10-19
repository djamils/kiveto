<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Vat;

use App\Context\Client\Infrastructure\Vat\ViesErrorMapper;
use App\Context\Client\Infrastructure\Vat\ViesVatNumberVerifier;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\ExternalApi\ExternalApiUserAgent;
use App\Shared\Infrastructure\ExternalApi\RetryAfterAwareClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

#[CoversClass(ViesVatNumberVerifier::class)]
final class ViesVatNumberVerifierTest extends TestCase
{
    public function testTheNumberIsPostedSplitByCountry(): void
    {
        $sent = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = [$method, $url, $options['body'] ?? null];

            return new MockResponse(self::fixture('valid'));
        }, 'https://ec.europa.eu/taxation_customs/vies/rest-api/');

        self::assertTrue($this->verifier($http)->verify('FR31523456788')->isValid);
        self::assertSame(['POST', 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number', '{"countryCode":"FR","vatNumber":"31523456788"}'], $sent);
    }

    public function testAnUnknownNumberIsInvalid(): void
    {
        self::assertFalse($this->verifier(new MockHttpClient(new MockResponse(self::fixture('invalid'))))->verify('FR12000000000')->isValid);
    }

    public function testAMemberStateOutageIsDown(): void
    {
        $this->expectException(ExternalServiceUnavailable::class);
        $this->expectExceptionMessage('vies is unavailable: MS_UNAVAILABLE.');

        $this->verifier(new MockHttpClient(new MockResponse(self::fixture('ms-unavailable'), ['http_code' => 200])))->verify('FR31523456788');
    }

    public function testTooManyConcurrentRequestsIsDown(): void
    {
        $this->expectException(ExternalServiceUnavailable::class);
        $this->expectExceptionMessage('GLOBAL_MAX_CONCURRENT_REQ');

        $this->verifier(new MockHttpClient(new MockResponse(self::fixture('global-max-concurrent'), ['http_code' => 400])))->verify('FR31523456788');
    }

    public function testAnInvalidInputIsAnOutageNotAnAccusation(): void
    {
        $this->expectException(ExternalServiceUnavailable::class);
        $this->expectExceptionMessage('vies is unavailable: INVALID_INPUT.');

        $this->verifier(new MockHttpClient(new MockResponse(self::fixture('invalid-input'), ['http_code' => 400])))->verify('FR00000000000');
    }

    public function testCallsArePacedAtOnePerSecond(): void
    {
        $verifier = $this->verifier(new MockHttpClient([new MockResponse(self::fixture('valid')), new MockResponse(self::fixture('valid'))]));

        $start = microtime(true);
        $verifier->verify('FR31523456788');
        $verifier->verify('FR31523456788');

        self::assertGreaterThanOrEqual(0.9, microtime(true) - $start);
    }

    public function testItCannotBeBuiltWithoutAContact(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('EXTERNAL_API_USER_AGENT must contain a contact (e-mail or URL) to call vies');

        $this->verifier(new MockHttpClient(), 'Kiveto/0.5');
    }

    private function verifier(MockHttpClient $http, string $userAgent = 'Kiveto-test (+tests@kiveto.invalid)'): ViesVatNumberVerifier
    {
        return new ViesVatNumberVerifier(
            new RetryAfterAwareClient($http, 'vies', new NullLogger(), $this->createStub(ClockInterface::class)),
            new ViesErrorMapper(new NullLogger()),
            new RateLimiterFactory(['id' => 'vies_test', 'policy' => 'token_bucket', 'limit' => 1, 'rate' => ['interval' => '1 second']], new InMemoryStorage()),
            new ExternalApiUserAgent($userAgent),
        );
    }

    private static function fixture(string $name): string
    {
        $content = file_get_contents(\dirname(__DIR__, 6) . '/tests/Fixtures/vies/' . $name . '.json');
        \assert(\is_string($content));

        return $content;
    }
}
