<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Messenger;

use App\Shared\Application\Bus\ExternalApiCommandInterface;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Event\IntegrationEventInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

/**
 * Domain events (and therefore the domain → integration bridges) must stay synchronous so
 * that integration events are written to the async transport inside the business
 * transaction. Asserted against the compiled senders locator, so YAML routes and
 * #[AsMessage] attributes are both covered. Commands calling external services
 * go to `async_external` only (batch senders override it per envelope).
 */
final class MessengerRoutingGuardTest extends KernelTestCase
{
    public function testDomainEventsAreOnlyRoutedToTheSyncTransport(): void
    {
        $classes = $this->concreteClassesImplementing(DomainEventInterface::class);
        self::assertNotEmpty($classes);

        foreach ($classes as $class) {
            $aliases = $this->senderAliases($class);

            self::assertSame([], array_diff($aliases, ['sync']), \sprintf('%s must be routed to "sync" only.', $class));
        }
    }

    public function testIntegrationEventsAreRoutedToTheAsyncTransport(): void
    {
        $classes = $this->concreteClassesImplementing(IntegrationEventInterface::class);
        self::assertNotEmpty($classes);

        foreach ($classes as $class) {
            self::assertSame(['async'], $this->senderAliases($class), \sprintf('%s must be routed to "async".', $class));
        }
    }

    public function testExternalApiCommandsAreRoutedToTheAsyncExternalTransportOnly(): void
    {
        $classes = $this->concreteClassesImplementing(ExternalApiCommandInterface::class, __DIR__ . '/Probe');
        self::assertNotEmpty($classes);

        foreach ($classes as $class) {
            self::assertSame(['async_external'], $this->senderAliases($class), \sprintf('%s must be routed to "async_external".', $class));
        }
    }

    /**
     * @param class-string $class
     *
     * @return list<string>
     */
    private function senderAliases(string $class): array
    {
        $locator = self::getContainer()->get('messenger.senders_locator');
        \assert($locator instanceof SendersLocatorInterface);

        $message = new \ReflectionClass($class)->newInstanceWithoutConstructor();
        $aliases = [];

        foreach ($locator->getSenders(new Envelope($message)) as $alias => $sender) {
            $aliases[] = (string) $alias;
        }

        return $aliases;
    }

    /**
     * @param class-string $interface
     *
     * @return list<class-string>
     */
    private function concreteClassesImplementing(string $interface, string ...$extraDirectories): array
    {
        $finder = new Finder()->files()->in([\dirname(__DIR__, 4) . '/src', ...$extraDirectories])->name('*.php');
        $found  = [];

        foreach ($finder as $file) {
            $content = $file->getContents();

            $hasNamespace = 1 === preg_match('/^namespace\s+([^;]+);/m', $content, $namespace);
            $hasClass     = 1 === preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)/m', $content, $class);

            if (!$hasNamespace || !$hasClass) {
                continue;
            }

            $fqcn = $namespace[1] . '\\' . $class[1];

            if (!class_exists($fqcn)) {
                continue;
            }

            $reflection = new \ReflectionClass($fqcn);

            if ($reflection->isInstantiable() && $reflection->implementsInterface($interface)) {
                $found[] = $reflection->getName();
            }
        }

        return $found;
    }
}
