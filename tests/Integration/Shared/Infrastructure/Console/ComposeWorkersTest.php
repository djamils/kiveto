<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * External-service calls have their own worker: mails and integration events
 * (`async`) never queue behind them, and the interactive queue is drained
 * before the batch one.
 */
final class ComposeWorkersTest extends TestCase
{
    public function testTheExternalWorkerConsumesTheInteractiveQueueBeforeTheBatchOne(): void
    {
        self::assertMatchesRegularExpression(
            '/messenger:consume async_external async_external_batch(\s|$)/',
            $this->command('external-worker'),
        );
    }

    public function testTheMessengerWorkerConsumesNoExternalQueue(): void
    {
        $command = $this->command('messenger-worker');

        self::assertMatchesRegularExpression('/messenger:consume async(\s|$)/', $command);
        self::assertStringNotContainsString('async_external', $command);
    }

    public function testBothWorkersRunInTheWorkersProfileAndRestart(): void
    {
        foreach (['messenger-worker', 'external-worker'] as $name) {
            $service = $this->service($name);

            self::assertSame(['workers'], $service['profiles'] ?? null, $name);
            self::assertSame('unless-stopped', $service['restart'] ?? null, $name);
        }
    }

    private function command(string $name): string
    {
        $command = $this->service($name)['command'] ?? null;
        self::assertIsString($command);

        return $command;
    }

    /**
     * @return array<mixed>
     */
    private function service(string $name): array
    {
        $compose = Yaml::parseFile(\dirname(__DIR__, 5) . '/compose.yaml');
        \assert(\is_array($compose) && \is_array($compose['services'] ?? null));

        $service = $compose['services'][$name] ?? null;
        self::assertIsArray($service, \sprintf('compose service "%s" is missing.', $name));

        return $service;
    }
}
