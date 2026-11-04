<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Security;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\System\IdentityAccess\Infrastructure\Security\ConfiguredPortalTestPhones;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\ErrorHandler\BufferingLogger;

final class ConfiguredPortalTestPhonesTest extends TestCase
{
    public function testNoNumberListedMeansNoTestPhone(): void
    {
        $logger = new BufferingLogger();

        self::assertNull(new ConfiguredPortalTestPhones('', '', $logger)->fixedCodeFor(PhoneNumber::fromString('+33639980001'), OtpPurpose::LOGIN));
        self::assertNull(new ConfiguredPortalTestPhones(' , ', '', $logger)->fixedCodeFor(PhoneNumber::fromString('+33639980001'), OtpPurpose::LOGIN));
        self::assertSame([], $logger->cleanLogs());
    }

    public function testAListedNumberGetsTheFixedCodeAndEachRequestIsLogged(): void
    {
        $logger = new BufferingLogger();
        $phones = new ConfiguredPortalTestPhones(' +33 6 39 98 00 01 ,+33639980002', '424242', $logger);

        self::assertSame('424242', $phones->fixedCodeFor(PhoneNumber::fromString('+33639980001'), OtpPurpose::LOGIN));
        self::assertSame('424242', $phones->fixedCodeFor(PhoneNumber::fromString('+33639980002'), OtpPurpose::ACCOUNT_DELETION));
        self::assertNull($phones->fixedCodeFor(PhoneNumber::fromString('+33612345678'), OtpPurpose::LOGIN));

        $message = 'Fixed code requested for a Portal test phone; nothing is sent.';
        self::assertSame([
            ['notice', $message, ['phone' => '+33639980001', 'purpose' => 'login']],
            ['notice', $message, ['phone' => '+33639980002', 'purpose' => 'account_deletion']],
        ], $logger->cleanLogs());
    }

    public function testAnInvalidNumberIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ConfiguredPortalTestPhones('0639980001', '424242', new NullLogger());
    }

    /** @return iterable<string, array{string}> */
    public static function provideACodeOtherThanSixDigitsIsRefusedCases(): iterable
    {
        yield 'empty' => [''];
        yield 'five digits' => ['42424'];
        yield 'seven digits' => ['4242424'];
        yield 'letters' => ['abcdef'];
        yield 'trailing newline' => ["424242\n"];
    }

    #[DataProvider('provideACodeOtherThanSixDigitsIsRefusedCases')]
    public function testACodeOtherThanSixDigitsIsRefused(string $code): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('PORTAL_TEST_OTP_CODE must be six digits when PORTAL_TEST_PHONES lists a number.');

        new ConfiguredPortalTestPhones('+33639980001', $code, new NullLogger());
    }
}
