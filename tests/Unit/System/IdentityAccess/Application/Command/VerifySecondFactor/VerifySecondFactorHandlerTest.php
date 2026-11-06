<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command\VerifySecondFactor;

use App\System\IdentityAccess\Application\Command\VerifySecondFactor\SecondFactorCheck;
use App\System\IdentityAccess\Application\Command\VerifySecondFactor\VerifySecondFactor;
use App\System\IdentityAccess\Application\Command\VerifySecondFactor\VerifySecondFactorHandler;
use App\System\IdentityAccess\Domain\SecondFactor\BackupCode;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactor;
use App\System\IdentityAccess\Domain\SecondFactor\Totp;
use App\System\IdentityAccess\Domain\SecondFactor\TotpSecret;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\SecondFactorFakes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VerifySecondFactorHandlerTest extends TestCase
{
    private const string ADMIN = '01960000-0000-7000-8000-000000003b01';

    private SecondFactorFakes $fakes;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->fakes = new SecondFactorFakes();
        // At 1111111111 the code of the RFC 6238 key is 050471.
        $this->clock = new FrozenClock(new \DateTimeImmutable('@1111111111'));
        $this->fakes->save(SecondFactor::reconstitute(
            UserId::fromString(self::ADMIN),
            TotpSecret::fromBytes(SecondFactorFakes::RFC_KEY),
            new \DateTimeImmutable('2026-11-01 08:00:00', new \DateTimeZone('UTC')),
            null,
            array_map(static fn (int $i): BackupCode => BackupCode::unused('h:backup:K7MQ2XW9P' . $i), range(0, 9)),
        ));
        $this->fakes->saves = 0;
    }

    public function testACodeOfTheAppIsAcceptedOnce(): void
    {
        self::assertSame(SecondFactorCheck::APP_CODE, $this->verify(' 050 471 '));
        self::assertSame([[self::ADMIN], 1], [$this->fakes->locked, $this->fakes->saves], 'read with a lock, saved');
        self::assertSame(Totp::stepAt($this->clock->now()), $this->fakes->factors[self::ADMIN]->lastUsedStep());

        self::assertSame(SecondFactorCheck::INVALID, $this->verify('050471'), 'replayed');
        self::assertSame(1, $this->fakes->saves, 'a refused code saves nothing');
    }

    public function testABackupCodeIsSpentOnce(): void
    {
        self::assertSame(SecondFactorCheck::BACKUP_CODE, $this->verify('k7mq2-xw9p3'));
        self::assertSame([1, 9], [$this->fakes->saves, $this->fakes->factors[self::ADMIN]->remainingBackupCodes()]);

        self::assertSame(SecondFactorCheck::INVALID, $this->verify('K7MQ2-XW9P3'), 'already spent');
        self::assertSame(1, $this->fakes->saves);
    }

    #[DataProvider('provideAWrongCodeIsRefusedWithoutAnErrorCases')]
    public function testAWrongCodeIsRefusedWithoutAnError(string $code): void
    {
        self::assertSame(SecondFactorCheck::INVALID, $this->verify($code));
        self::assertSame(0, $this->fakes->saves);
        self::assertNull($this->fakes->factors[self::ADMIN]->lastUsedStep());
        self::assertSame(10, $this->fakes->factors[self::ADMIN]->remainingBackupCodes());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAWrongCodeIsRefusedWithoutAnErrorCases(): iterable
    {
        yield 'empty' => [''];

        yield 'blank' => ['   '];

        yield 'wrong app code' => ['123456'];

        yield 'eight digits' => ['12345678'];

        yield 'unknown backup code' => ['ZZZZZ-ZZZZZ'];
    }

    public function testAnAccountWithoutSecondFactorIsReportedSo(): void
    {
        self::assertSame(SecondFactorCheck::NOT_ENABLED, new VerifySecondFactorHandler($this->fakes, $this->fakes->backupCodes(), $this->clock)(
            new VerifySecondFactor('01960000-0000-7000-8000-000000003bff', '050471'),
        ));
        self::assertSame(0, $this->fakes->saves);
    }

    #[DataProvider('provideOnlyACodeOfTheAppOrABackupCodeIsAcceptedCases')]
    public function testOnlyACodeOfTheAppOrABackupCodeIsAccepted(SecondFactorCheck $check, bool $accepted): void
    {
        self::assertSame($accepted, $check->isAccepted());
    }

    /**
     * @return iterable<string, array{SecondFactorCheck, bool}>
     */
    public static function provideOnlyACodeOfTheAppOrABackupCodeIsAcceptedCases(): iterable
    {
        yield 'app code' => [SecondFactorCheck::APP_CODE, true];

        yield 'backup code' => [SecondFactorCheck::BACKUP_CODE, true];

        yield 'invalid' => [SecondFactorCheck::INVALID, false];

        yield 'not enabled' => [SecondFactorCheck::NOT_ENABLED, false];
    }

    private function verify(string $code): SecondFactorCheck
    {
        return new VerifySecondFactorHandler($this->fakes, $this->fakes->backupCodes(), $this->clock)(new VerifySecondFactor(self::ADMIN, $code));
    }
}
