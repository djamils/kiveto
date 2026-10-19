<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\ExternalApi;

use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\MonologBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;

/**
 * External calls are traced on `external_api`; Symfony's `http_client` channel
 * (full URLs, query strings included) must reach no handler in dev or prod.
 */
final class LoggingConfigurationTest extends KernelTestCase
{
    public function testTheExternalApiChannelExists(): void
    {
        $logger = self::getContainer()->get('test.monolog.logger.external_api');

        self::assertInstanceOf(LoggerInterface::class, $logger);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTheHttpClientChannelReachesNoHandlerCases(): iterable
    {
        yield 'dev' => ['dev'];
        yield 'prod' => ['prod'];
    }

    #[DataProvider('provideTheHttpClientChannelReachesNoHandlerCases')]
    public function testTheHttpClientChannelReachesNoHandler(string $environment): void
    {
        $handlers = $this->handlers($environment);
        self::assertNotEmpty($handlers);

        $nested = [];

        foreach ($handlers as $handler) {
            if (isset($handler['handler']) && \is_string($handler['handler'])) {
                $nested[] = $handler['handler'];
            }
        }

        foreach ($handlers as $name => $handler) {
            if (\in_array($name, $nested, true)) {
                // Fed only by its parent handler, which carries the channel filter.
                continue;
            }

            self::assertFalse($this->accepts($handler, 'http_client'), \sprintf('%s handler "%s" must exclude http_client.', $environment, $name));
        }
    }

    public function testProdWritesTheExternalApiChannelAtInfoLevel(): void
    {
        $handler = $this->handlers('prod')['external_api'] ?? null;

        self::assertIsArray($handler);
        self::assertSame('stream', $handler['type']);
        self::assertSame('php://stderr', $handler['path']);
        self::assertSame('info', strtolower((string) (\is_scalar($handler['level']) ? $handler['level'] : '')));
        self::assertSame('monolog.formatter.json', $handler['formatter']);
        self::assertSame(['type' => 'inclusive', 'elements' => ['external_api']], $handler['channels']);
    }

    /**
     * @param array<mixed> $handler
     */
    private function accepts(array $handler, string $channel): bool
    {
        $channels = $handler['channels'] ?? null;

        if (!\is_array($channels)) {
            return true;
        }

        $elements = \is_array($channels['elements'] ?? null) ? $channels['elements'] : [];
        $listed   = \in_array($channel, $elements, true);

        return 'exclusive' === ($channels['type'] ?? null) ? !$listed : $listed;
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function handlers(string $environment): array
    {
        $file = Yaml::parseFile(\dirname(__DIR__, 5) . '/config/packages/monolog.yaml');
        \assert(\is_array($file));

        $configs = [];

        foreach ([$file, $file['when@' . $environment] ?? []] as $section) {
            if (\is_array($section) && \is_array($section['monolog'] ?? null)) {
                $configs[] = $section['monolog'];
            }
        }

        $processed = new Processor()->processConfiguration(new Configuration(), $configs);
        $handlers  = $processed['handlers'] ?? [];
        \assert(\is_array($handlers));

        $result = [];

        foreach ($handlers as $name => $handler) {
            \assert(\is_array($handler));
            $result[(string) $name] = $handler;
        }

        return $result;
    }
}
