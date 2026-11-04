<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Health;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

/**
 * /health and /health/ready answer on every host, without a session, for
 * the container healthchecks, the deploy script and the external probe.
 */
final class HealthEndpointsTest extends WebTestCase
{
    /** @return iterable<string, array{string}> */
    public static function provideTheProbesAnswerOnEveryHostCases(): iterable
    {
        yield 'clinic' => ['http://clinic.kiveto.local'];
        yield 'portal' => ['http://portal.kiveto.local'];
        yield 'backoffice' => ['http://backoffice.kiveto.local'];
        yield 'inside the container' => ['http://localhost:8080'];
    }

    #[DataProvider('provideTheProbesAnswerOnEveryHostCases')]
    public function testTheProbesAnswerOnEveryHost(string $origin): void
    {
        $client = self::createClient();

        $client->request('GET', $origin . '/health');
        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'ok'], self::body($client->getResponse()->getContent()));
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertFalse($client->getResponse()->headers->has('Set-Cookie'), 'no session');

        $client->request('GET', $origin . '/health/ready');
        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'ok', 'checks' => ['database' => 'ok', 'messenger' => 'ok']], self::body($client->getResponse()->getContent()));
        self::assertFalse($client->getResponse()->headers->has('Set-Cookie'), 'no session');
    }

    /**
     * Every transport with a queue is probed: a transport added to messenger.yaml
     * without being added to `health.messenger_transports` fails here.
     */
    public function testEveryQueueOfMessengerIsProbed(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $receivers = $container->get('messenger.receiver_locator');
        \assert($receivers instanceof ServiceLocator);

        $countable = [];
        foreach (array_keys($receivers->getProvidedServices()) as $name) {
            if (!str_starts_with($name, 'messenger.transport.') && !str_starts_with($name, 'scheduler_') && $receivers->get($name) instanceof MessageCountAwareInterface) {
                $countable[] = $name;
            }
        }
        sort($countable);

        $probed = $container->getParameter('health.messenger_transports');
        \assert(\is_array($probed));
        sort($probed);

        self::assertSame($countable, $probed);
    }

    /** @return array<mixed> */
    private static function body(string|false $content): array
    {
        $body = json_decode((string) $content, true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($body));

        return $body;
    }
}
