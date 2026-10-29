<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use App\Tests\Support\RecordingCommandBus;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;

/**
 * Drives the Portal API with absolute portal-host URLs. The rate limiters
 * are the real ones (database-backed, rolled back with the test): each test
 * uses its own phones and client IP.
 */
abstract class PortalApiTestCase extends WebTestCase
{
    use Factories;

    protected const string BASE = 'http://portal.kiveto.local/api/portal/v1';

    protected KernelBrowser $browser;

    protected string $clientIp = '203.0.113.10';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $server
     */
    protected function post(string $path, array $body, array $server = []): Response
    {
        $this->browser->request('POST', self::BASE . $path, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT'  => 'application/json',
            'REMOTE_ADDR'  => $this->clientIp,
            ...$server,
        ], content: json_encode($body, \JSON_THROW_ON_ERROR));

        return $this->browser->getResponse();
    }

    /**
     * @param array<string, string> $server
     */
    protected function get(string $path, array $server = []): Response
    {
        $this->browser->request('GET', self::BASE . $path, server: ['HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => $this->clientIp, ...$server]);

        return $this->browser->getResponse();
    }

    /**
     * @return array<string, mixed>
     */
    protected static function json(Response $response, int $status): array
    {
        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($data));

        $fields = [];
        foreach ($data as $key => $value) {
            $fields[(string) $key] = $value;
        }

        return $fields;
    }

    /** Asserts a problem+json answer and returns its business code. */
    protected static function problemType(Response $response, int $status): string
    {
        self::assertStringStartsWith('application/problem+json', (string) $response->headers->get('Content-Type'));
        $type = self::json($response, $status)['type'] ?? null;
        self::assertIsString($type);

        return $type;
    }

    protected function bus(): RecordingCommandBus
    {
        $bus = $this->browser->getContainer()->get(RecordingCommandBus::class);
        \assert($bus instanceof RecordingCommandBus);

        return $bus;
    }

    /** The code carried by the last one-time-code SMS requested. */
    protected function lastOtpCode(): string
    {
        $sms = array_values(array_filter($this->bus()->recorded(RequestNotification::class), static fn (RequestNotification $n): bool => 'PORTAL_OTP' === $n->purpose));
        self::assertNotSame([], $sms, 'no code was sent');
        $code = $sms[\count($sms) - 1]->parameters['code'] ?? null;
        self::assertIsString($code);

        return $code;
    }

    protected function otpSmsCount(): int
    {
        return \count(array_filter($this->bus()->recorded(RequestNotification::class), static fn (RequestNotification $n): bool => 'PORTAL_OTP' === $n->purpose));
    }

    /** Requests a sign-in code and returns its challenge id. */
    protected function requestCode(string $phone): string
    {
        $challengeId = self::json($this->post('/auth/otp/request', ['phone' => $phone]), 202)['challengeId'] ?? null;
        self::assertIsString($challengeId);

        return $challengeId;
    }

    /**
     * Signs in through a code and returns the authenticated answer.
     *
     * @return array<string, mixed>
     */
    protected function signIn(string $phone): array
    {
        $challengeId = $this->requestCode($phone);
        $answer      = self::json($this->post('/auth/otp/verify', ['challengeId' => $challengeId, 'code' => $this->lastOtpCode()]), 200);
        self::assertSame('authenticated', $answer['status']);

        return $answer;
    }

    protected static function string(mixed $value): string
    {
        self::assertIsString($value);

        return $value;
    }

    /**
     * @param list<mixed> $params
     */
    protected function number(string $sql, array $params = []): int
    {
        $value = $this->connection()->fetchOne($sql, $params);
        self::assertIsNumeric($value, $sql);

        return (int) $value;
    }

    /**
     * @param array<string, mixed> $problem
     */
    protected static function firstViolationPath(array $problem): ?string
    {
        $violations = $problem['violations'] ?? null;
        self::assertIsArray($violations);
        $first = $violations[0] ?? null;
        self::assertIsArray($first);
        $path = $first['propertyPath'] ?? null;

        return \is_string($path) ? $path : null;
    }

    protected function connection(): Connection
    {
        $connection = $this->browser->getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
