<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

final class MessengerHandlersRegistrationTest extends TestCase
{
    /**
     * This test ensures all handlers have the #[AsMessageHandler] attribute.
     * Without this attribute, the handler won't be registered in Symfony Messenger.
     * Handlers of external-call commands are bound to their own bus instead.
     */
    public function testAllHandlersHaveAsMessageHandlerAttribute(): void
    {
        $srcPath = \dirname(__DIR__, 3) . '/src';

        $finder = new Finder();
        $finder->files()
            ->in($srcPath)
            ->name('*Handler.php')
            ->path('/Application\/(Command|Query)\//')
            ->notPath('/Exception/')
        ;

        $handlersWithoutAttribute = [];

        foreach ($finder as $file) {
            $content = $file->getContents();

            // Skip if it's not a handler class (final readonly class *Handler)
            if (!preg_match('/final\s+readonly\s+class\s+\w+Handler/', $content)) {
                continue;
            }

            // Check if it has the AsMessageHandler attribute
            $registered = str_contains($content, '#[AsMessageHandler]')
                || str_contains($content, "#[AsMessageHandler(bus: 'messenger.bus.external_api')]");

            if (!$registered) {
                $handlersWithoutAttribute[] = $file->getRelativePathname();
            }
        }

        self::assertEmpty(
            $handlersWithoutAttribute,
            \sprintf(
                "The following handlers are missing the #[AsMessageHandler] attribute:\n- %s",
                implode("\n- ", $handlersWithoutAttribute)
            )
        );
    }
}
