<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Presentation\Twig;

use App\Shared\Presentation\Twig\PhoneFormatRuntime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneFormatRuntimeTest extends TestCase
{
    private PhoneFormatRuntime $runtime;

    protected function setUp(): void
    {
        $this->runtime = new PhoneFormatRuntime();
    }

    // ── Local mode (default) ──

    #[DataProvider('provideLocalDisplayCases')]
    public function testLocalDisplay(string $input, string $expectedContains): void
    {
        $result = $this->runtime->phoneDisplay($input);

        self::assertStringContainsString($expectedContains, $result);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideLocalDisplayCases(): iterable
    {
        yield 'FR mobile' => ['+33612345678', '06 12 34 56 78'];
        yield 'FR landline' => ['+33142567890', '01 42 56 78 90'];
        yield 'BE mobile' => ['+32471234567', '047 12 34 56'];
        yield 'CH mobile' => ['+41791234567', '07 912 34 567'];
        yield 'US number' => ['+12015550123', '(201) 555-0123'];
        yield 'GB mobile' => ['+447911123456', '0791 112 3456'];
        yield 'MA mobile' => ['+212612345678', '06 12 34 56 78'];
        yield 'IT landline' => ['+390612345678', '061 234 5678'];
        yield 'Unknown country' => ['+999123456789', '+999123456789'];
    }

    public function testLocalModeHasNoDialCode(): void
    {
        $result = $this->runtime->phoneDisplay('+33612345678');

        self::assertStringContainsString('fi-fr', $result);
        self::assertStringContainsString('06 12 34 56 78', $result);
        self::assertStringNotContainsString('+33', $result);
    }

    // ── International mode ──

    #[DataProvider('provideInternationalDisplayCases')]
    public function testInternationalDisplay(string $input, string $expectedContains): void
    {
        $result = $this->runtime->phoneDisplay($input, true);

        self::assertStringContainsString($expectedContains, $result);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInternationalDisplayCases(): iterable
    {
        yield 'FR mobile — no trunk' => ['+33612345678', '+33 6 12 34 56 78'];
        yield 'BE mobile — no trunk' => ['+32471234567', '+32 47 12 34 567'];
        yield 'CH mobile — no trunk' => ['+41791234567', '+41 7 912 34 567'];
        yield 'DE mobile — no trunk' => ['+4915112345678', '+49 15 1123456'];
        yield 'GB mobile — no trunk' => ['+447911123456', '+44 7911 123 456'];
        yield 'US number — no trunk' => ['+12015550123', '+1 (201) 555-0123'];
        yield 'IT landline — keeps trunk' => ['+390612345678', '+39 061 234 5678'];
    }

    public function testInternationalFrenchHasDialCodeNoTrunk(): void
    {
        $result = $this->runtime->phoneDisplay('+33612345678', true);

        self::assertStringContainsString('fi-fr', $result);
        self::assertStringContainsString('+33 6 12 34 56 78', $result);
    }

    public function testInternationalItalyKeepsTrunk(): void
    {
        $result = $this->runtime->phoneDisplay('+390612345678', true);

        self::assertStringContainsString('fi-it', $result);
        self::assertStringContainsString('+39 061', $result);
    }

    // ── Edge cases ──

    public function testEmptyStringReturnsEmpty(): void
    {
        self::assertSame('', $this->runtime->phoneDisplay(''));
    }

    public function testNonE164ReturnsSanitized(): void
    {
        self::assertSame('0612345678', $this->runtime->phoneDisplay('0612345678'));
    }

    public function testHtmlIsSanitized(): void
    {
        $result = $this->runtime->phoneDisplay('<script>');

        self::assertStringNotContainsString('<script>', $result);
        self::assertStringContainsString('&lt;script&gt;', $result);
    }
}
