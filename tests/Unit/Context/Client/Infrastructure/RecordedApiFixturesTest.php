<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The HTTP adapters are tested against answers recorded from the real
 * services, not against what we believe they answer.
 */
final class RecordedApiFixturesTest extends TestCase
{
    /**
     * Fixtures that stay hand-written on purpose:
     * - siren-non-diffusible: a non-diffusible company is almost always a sole
     *   trader, whose identity is personal data and has no place in the repository;
     * - the VIES errors: VIES outages cannot be triggered on demand.
     */
    private const array HAND_WRITTEN = [
        'recherche-entreprises/siren-non-diffusible.json',
        'vies/global-max-concurrent.json',
        'vies/invalid-input.json',
        'vies/ms-unavailable.json',
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEveryOtherFixtureIsARecordedAnswerCases(): iterable
    {
        $root = \dirname(__DIR__, 5) . '/tests/Fixtures';

        foreach (['recherche-entreprises', 'vies'] as $service) {
            foreach (glob($root . '/' . $service . '/*.json') ?: [] as $file) {
                $name = $service . '/' . basename($file);

                if (!\in_array($name, self::HAND_WRITTEN, true)) {
                    yield $name => [$file];
                }
            }
        }
    }

    #[DataProvider('provideEveryOtherFixtureIsARecordedAnswerCases')]
    public function testEveryOtherFixtureIsARecordedAnswer(string $file): void
    {
        $content = file_get_contents($file);
        self::assertIsString($content);
        self::assertStringNotContainsString('"_source": "hand-written', $content, basename($file) . ' must be replaced by a recorded answer.');
        self::assertIsArray(json_decode($content, true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testTheHandWrittenFixturesStillExist(): void
    {
        $root = \dirname(__DIR__, 5) . '/tests/Fixtures';

        foreach (self::HAND_WRITTEN as $name) {
            self::assertFileExists($root . '/' . $name);
        }
    }
}
