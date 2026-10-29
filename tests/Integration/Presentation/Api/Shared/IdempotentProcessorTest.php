<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Shared;

use App\Tests\Fixtures\Api\IdempotentEchoProcessor;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * D10/D12: the Idempotency-Key decorator and the problem+json rendering of
 * ApiProblem, through the test-only resource of tests/Fixtures/Api.
 */
final class IdempotentProcessorTest extends WebTestCase
{
    private const string BASE = 'http://portal.kiveto.local/api/portal/v1/_test';

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();
    }

    public function testAMissingKeyIsRejectedWithAProblem(): void
    {
        $before = IdempotentEchoProcessor::executions();

        $problem = $this->problem($this->post('/idempotent-echo', '{"message":"bonjour"}'), 400);

        self::assertSame('idempotency_key_missing', $problem['type']);
        self::assertSame('Idempotency key missing', $problem['title']);
        self::assertSame(400, $problem['status']);
        self::assertSame($before, IdempotentEchoProcessor::executions());
    }

    public function testAMalformedKeyIsRejectedAsMissing(): void
    {
        $problem = $this->problem($this->post('/idempotent-echo', '{"message":"bonjour"}', str_repeat('k', 129)), 400);

        self::assertSame('idempotency_key_missing', $problem['type']);
    }

    public function testARetryReplaysTheStoredResponseWithoutRunningTheOperation(): void
    {
        $first = $this->post('/idempotent-echo', '{"message":"bonjour"}', 'key-replay');
        self::assertSame(201, $first->getStatusCode());
        self::assertNull($first->headers->get('Idempotent-Replayed'));
        $body = $this->json($first->getContent());
        self::assertSame('bonjour', $body['message']);
        $executions = IdempotentEchoProcessor::executions();
        self::assertSame($executions, $body['executions']);

        $second = $this->post('/idempotent-echo', '{"message":"bonjour"}', 'key-replay');

        self::assertSame(201, $second->getStatusCode());
        self::assertSame('true', $second->headers->get('Idempotent-Replayed'));
        self::assertSame('application/json', $second->headers->get('Content-Type'));
        self::assertSame($body, $this->json($second->getContent()));
        self::assertSame($executions, IdempotentEchoProcessor::executions());
        self::assertSame(1, $this->storedKeys());
    }

    public function testTheSameKeyWithAnotherPayloadIsRejected(): void
    {
        $this->post('/idempotent-echo', '{"message":"bonjour"}', 'key-reused');
        $executions = IdempotentEchoProcessor::executions();

        $problem = $this->problem($this->post('/idempotent-echo', '{"message":"au revoir"}', 'key-reused'), 422);

        self::assertSame('idempotency_key_reused', $problem['type']);
        self::assertSame($executions, IdempotentEchoProcessor::executions());
    }

    public function testDistinctKeysRunTheOperationEachTime(): void
    {
        $first  = $this->json($this->post('/idempotent-echo', '{"message":"bonjour"}', 'key-a')->getContent());
        $second = $this->json($this->post('/idempotent-echo', '{"message":"bonjour"}', 'key-b')->getContent());

        self::assertSame(\is_int($first['executions']) ? $first['executions'] + 1 : null, $second['executions']);
    }

    public function testAFailedOperationIsNotStoredAndRendersItsProblem(): void
    {
        $response = $this->post('/idempotent-echo', '{"message":"rate-limited"}', 'key-failure');
        $problem  = $this->problem($response, 429);

        self::assertSame('otp_rate_limited', $problem['type']);
        self::assertSame('Too many requests', $problem['title']);
        self::assertSame('Réessayez plus tard.', $problem['detail']);
        self::assertSame('120', $response->headers->get('Retry-After'));
        self::assertSame(0, $this->storedKeys());

        $executions = IdempotentEchoProcessor::executions();
        $this->problem($this->post('/idempotent-echo', '{"message":"rate-limited"}', 'key-failure'), 429);
        self::assertSame($executions + 1, IdempotentEchoProcessor::executions());
    }

    public function testOperationsWithoutTheFlagIgnoreTheHeader(): void
    {
        $executions = IdempotentEchoProcessor::executions();

        self::assertSame(201, $this->post('/echo', '{"message":"bonjour"}')->getStatusCode());
        self::assertSame(201, $this->post('/echo', '{"message":"bonjour"}', 'key-ignored')->getStatusCode());
        self::assertSame(201, $this->post('/echo', '{"message":"bonjour"}', 'key-ignored')->getStatusCode());

        self::assertSame($executions + 3, IdempotentEchoProcessor::executions());
        self::assertSame(0, $this->storedKeys());
    }

    private function post(string $path, string $content, ?string $key = null): Response
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if (null !== $key) {
            $server['HTTP_IDEMPOTENCY_KEY'] = $key;
        }

        $this->browser->request('POST', self::BASE . $path, server: $server, content: $content);

        return $this->browser->getResponse();
    }

    /**
     * @return array<string, mixed>
     */
    private function problem(Response $response, int $status): array
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('Content-Type'));

        return $this->json($response->getContent());
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string|false $content): array
    {
        $decoded = json_decode((string) $content, true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($decoded));

        $result = [];
        foreach ($decoded as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    private function storedKeys(): int
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        $count = $connection->fetchOne('SELECT COUNT(*) FROM shared__idempotency_keys');

        return (int) (\is_scalar($count) ? $count : 0);
    }
}
