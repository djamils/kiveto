<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Routing;

use App\Shared\Infrastructure\Routing\KivetoDomainEnvVarProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

final class KivetoDomainEnvVarProcessorTest extends TestCase
{
    private const string PATTERN = 'kiveto\.local|staging\.kiveto\.app|demo\.kiveto\.app|kiveto\.app';

    /** @return iterable<string, array{string}> */
    public static function provideReturnsAKivetoDomainCases(): iterable
    {
        yield 'dev and tests' => ['kiveto.local'];
        yield 'staging' => ['staging.kiveto.app'];
        yield 'demo' => ['demo.kiveto.app'];
        yield 'production' => ['kiveto.app'];
    }

    #[DataProvider('provideReturnsAKivetoDomainCases')]
    public function testReturnsAKivetoDomain(string $domain): void
    {
        self::assertSame($domain, $this->read($domain));
    }

    /** @return iterable<string, array{mixed}> */
    public static function provideRefusesAnyOtherValueCases(): iterable
    {
        yield 'empty' => [''];
        yield 'unknown domain' => ['example.com'];
        yield 'a host, not a domain' => ['portal.kiveto.app'];
        yield 'a domain plus a suffix' => ['kiveto.app.evil.test'];
        yield 'unescaped dot' => ['kivetoxapp'];
        yield 'trailing newline' => ["kiveto.app\n"];
        yield 'not a string' => [42];
    }

    #[DataProvider('provideRefusesAnyOtherValueCases')]
    public function testRefusesAnyOtherValue(mixed $value): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Env var "KIVETO_DOMAIN" must be one of the Kiveto domains (kiveto.local|staging.kiveto.app|demo.kiveto.app|kiveto.app)');

        $this->read($value);
    }

    public function testProvidesTheKivetoDomainPrefix(): void
    {
        self::assertSame(['kiveto_domain' => 'string'], KivetoDomainEnvVarProcessor::getProvidedTypes());
    }

    private function read(mixed $value): string
    {
        return new KivetoDomainEnvVarProcessor(self::PATTERN)
            ->getEnv('kiveto_domain', 'KIVETO_DOMAIN', static fn (string $name): mixed => 'KIVETO_DOMAIN' === $name ? $value : null)
        ;
    }
}
