<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Messenger;

use App\Shared\Application\Bus\ExternalApiCommandInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocator;

/**
 * Pairs the external-call bus with its messages: every handler on
 * `messenger.bus.external_api` handles an ExternalApiCommandInterface, every
 * such command has a handler there and none on the command bus (where it
 * would run inside a transaction). Scans src/ and the test probes.
 */
final class ExternalApiBusGuardTest extends KernelTestCase
{
    public function testEveryExternalApiCommandHasAHandlerOnTheExternalApiBusOnly(): void
    {
        $commands = $this->classes(static fn (\ReflectionClass $class): bool => $class->isInstantiable()
            && $class->implementsInterface(ExternalApiCommandInterface::class));
        self::assertNotEmpty($commands);

        foreach ($commands as $class) {
            self::assertGreaterThan(0, $this->handlerCount('messenger.bus.external_api', $class), \sprintf('%s needs a handler on messenger.bus.external_api.', $class));
            // A bare #[AsMessageHandler] lands on every bus, the command bus included.
            self::assertSame(0, $this->handlerCount('messenger.bus.command', $class), \sprintf('%s must not be handled on the command bus.', $class));
        }
    }

    public function testEveryHandlerOnTheExternalApiBusHandlesAnExternalApiCommand(): void
    {
        $handlers = $this->classes(static function (\ReflectionClass $class): bool {
            foreach ($class->getAttributes(AsMessageHandler::class) as $attribute) {
                if ('messenger.bus.external_api' === $attribute->newInstance()->bus) {
                    return true;
                }
            }

            return false;
        });
        self::assertNotEmpty($handlers);

        foreach ($handlers as $handler) {
            $type = new \ReflectionMethod($handler, '__invoke')->getParameters()[0]->getType();

            self::assertInstanceOf(\ReflectionNamedType::class, $type, $handler);
            self::assertTrue(
                is_a($type->getName(), ExternalApiCommandInterface::class, true),
                \sprintf('%s is bound to messenger.bus.external_api but does not handle an ExternalApiCommandInterface.', $handler),
            );
        }
    }

    /**
     * @param class-string $message
     */
    private function handlerCount(string $bus, string $message): int
    {
        $locator = self::getContainer()->get($bus . '.messenger.handlers_locator');
        \assert($locator instanceof HandlersLocator);

        $message = new \ReflectionClass($message)->newInstanceWithoutConstructor();

        return iterator_count((static fn (): \Generator => yield from $locator->getHandlers(new Envelope($message)))());
    }

    /**
     * @param callable(\ReflectionClass<object>): bool $filter
     *
     * @return list<class-string>
     */
    private function classes(callable $filter): array
    {
        $root   = \dirname(__DIR__, 4);
        $finder = new Finder()->files()->in([$root . '/src', __DIR__ . '/Probe'])->name('*.php');
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

            if ($filter($reflection)) {
                $found[] = $reflection->getName();
            }
        }

        return $found;
    }
}
