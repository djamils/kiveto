<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain\SecondFactor;

use App\System\IdentityAccess\Domain\SecondFactor\Totp;
use App\System\IdentityAccess\Domain\SecondFactor\TotpSecret;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    /** The SHA-1 key of RFC 6238, appendix B. */
    private const string RFC_KEY = '12345678901234567890';

    /**
     * RFC 6238 appendix B (SHA-1): the 8-digit values cut to the 6 digits
     * authenticator apps show.
     */
    #[DataProvider('provideMakesTheRfc6238CodesCases')]
    public function testMakesTheRfc6238Codes(int $timestamp, string $code): void
    {
        $at = new \DateTimeImmutable('@' . $timestamp);

        self::assertSame($code, Totp::code(self::secret(), Totp::stepAt($at)));
        self::assertSame(intdiv($timestamp, 30), Totp::matchingStep(self::secret(), $code, $at, null));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function provideMakesTheRfc6238CodesCases(): iterable
    {
        yield 'T=59' => [59, '287082'];

        yield 'T=1111111109' => [1111111109, '081804'];

        yield 'T=1111111111' => [1111111111, '050471'];

        yield 'T=1234567890' => [1234567890, '005924'];

        yield 'T=2000000000' => [2000000000, '279037'];

        yield 'T=20000000000' => [20000000000, '353130'];
    }

    public function testAStepLastsThirtySeconds(): void
    {
        self::assertSame([0, 0, 1, 37037037], [
            Totp::stepAt(new \DateTimeImmutable('@0')),
            Totp::stepAt(new \DateTimeImmutable('@29')),
            Totp::stepAt(new \DateTimeImmutable('@30')),
            Totp::stepAt(new \DateTimeImmutable('@1111111111')),
        ]);
        self::assertSame([30, 6], [Totp::PERIOD, Totp::DIGITS]);
    }

    public function testACodeOneStepEarlyOrLateIsAccepted(): void
    {
        $now     = new \DateTimeImmutable('@1111111111');
        $current = Totp::stepAt($now);

        self::assertSame($current - 1, Totp::matchingStep(self::secret(), Totp::code(self::secret(), $current - 1), $now, null));
        self::assertSame($current + 1, Totp::matchingStep(self::secret(), Totp::code(self::secret(), $current + 1), $now, null));
        self::assertNull(Totp::matchingStep(self::secret(), Totp::code(self::secret(), $current - 2), $now, null));
        self::assertNull(Totp::matchingStep(self::secret(), Totp::code(self::secret(), $current + 2), $now, null));
    }

    public function testACodeIsNeverAcceptedAtOrBeforeTheLastStepUsed(): void
    {
        $now     = new \DateTimeImmutable('@1111111111');
        $current = Totp::stepAt($now);

        self::assertNull(Totp::matchingStep(self::secret(), Totp::code(self::secret(), $current), $now, $current));
        self::assertNull(Totp::matchingStep(self::secret(), Totp::code(self::secret(), $current - 1), $now, $current - 1));
        self::assertSame($current, Totp::matchingStep(self::secret(), Totp::code(self::secret(), $current), $now, $current - 1));
        self::assertSame($current + 1, Totp::matchingStep(self::secret(), Totp::code(self::secret(), $current + 1), $now, $current));
    }

    public function testSpacesInTheTypedCodeDoNotCount(): void
    {
        $now = new \DateTimeImmutable('@59');

        self::assertSame(1, Totp::matchingStep(self::secret(), ' 287 082 ', $now, null));
        self::assertSame(1, Totp::matchingStep(self::secret(), "287\t082", $now, null));
    }

    #[DataProvider('provideRefusesWhatIsNotSixDigitsCases')]
    public function testRefusesWhatIsNotSixDigits(string $typed): void
    {
        self::assertNull(Totp::matchingStep(self::secret(), $typed, new \DateTimeImmutable('@59'), null));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRefusesWhatIsNotSixDigitsCases(): iterable
    {
        yield 'empty' => [''];

        yield 'five digits' => ['28708'];

        yield 'the eight digits of the RFC' => ['94287082'];

        yield 'letters' => ['28708a'];

        yield 'a dash' => ['287-082'];

        yield 'a wrong code' => ['287083'];
    }

    private static function secret(): TotpSecret
    {
        return TotpSecret::fromBytes(self::RFC_KEY);
    }
}
