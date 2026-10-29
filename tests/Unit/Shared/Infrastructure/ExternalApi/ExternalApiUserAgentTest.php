<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\ExternalApi;

use App\Shared\Infrastructure\ExternalApi\ExternalApiUserAgent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExternalApiUserAgent::class)]
final class ExternalApiUserAgentTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAUserAgentWithAContactIsReturnedAsIsCases(): iterable
    {
        yield 'e-mail' => ['Kiveto/0.5 (contact@kiveto.app)'];
        yield 'e-mail with a plus' => ['Kiveto-test (+tests@kiveto.invalid)'];
        yield 'https URL' => ['Kiveto/0.5 (+https://kiveto.app/contact)'];
        yield 'http URL' => ['Kiveto/0.5 http://kiveto.app'];
    }

    #[DataProvider('provideAUserAgentWithAContactIsReturnedAsIsCases')]
    public function testAUserAgentWithAContactIsReturnedAsIs(string $value): void
    {
        self::assertSame($value, new ExternalApiUserAgent($value)->withContact('registry'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAUserAgentWithoutContactNamesTheVariableAndTheServiceCases(): iterable
    {
        yield 'bare product' => ['Kiveto/0.5'];
        yield 'empty' => [''];
        yield 'at sign without domain' => ['Kiveto @team'];
        yield 'scheme without host' => ['Kiveto https://'];
    }

    #[DataProvider('provideAUserAgentWithoutContactNamesTheVariableAndTheServiceCases')]
    public function testAUserAgentWithoutContactNamesTheVariableAndTheService(string $value): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('EXTERNAL_API_USER_AGENT must contain a contact (e-mail or URL) to call vies');

        new ExternalApiUserAgent($value)->withContact('vies');
    }
}
