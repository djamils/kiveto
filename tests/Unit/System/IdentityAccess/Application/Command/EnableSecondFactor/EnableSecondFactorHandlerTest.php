<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command\EnableSecondFactor;

use App\System\IdentityAccess\Application\Command\EnableSecondFactor\EnableSecondFactor;
use App\System\IdentityAccess\Application\Command\EnableSecondFactor\EnableSecondFactorHandler;
use App\System\IdentityAccess\Domain\SecondFactor\BackupCode;
use App\System\IdentityAccess\Domain\SecondFactor\InvalidSecondFactorCodeException;
use App\System\IdentityAccess\Domain\SecondFactor\Totp;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryUsers;
use App\Tests\Unit\System\IdentityAccess\Support\SecondFactorFakes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EnableSecondFactorHandlerTest extends TestCase
{
    private const string ADMIN  = '01960000-0000-7000-8000-000000003a01';
    private const string CLINIC = '01960000-0000-7000-8000-000000003a02';

    /** Base32 of the RFC 6238 key; at 1111111111 its code is 050471. */
    private const string SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private InMemoryUsers $users;
    private SecondFactorFakes $fakes;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->users = new InMemoryUsers();
        $this->fakes = new SecondFactorFakes();
        $this->clock = new FrozenClock(new \DateTimeImmutable('@1111111111'));

        foreach ([self::ADMIN => UserType::BACKOFFICE, self::CLINIC => UserType::CLINIC] as $id => $type) {
            $this->users->save(User::reconstitute(UserId::fromString($id), $id . '@kiveto.local', '$h', new \DateTimeImmutable(), UserStatus::ACTIVE, null, $type));
        }
    }

    public function testTheProvenKeyIsStoredWithTenBackupCodes(): void
    {
        $plain = $this->handler()(new EnableSecondFactor(self::ADMIN, self::SECRET, '050471'));

        self::assertCount(10, $plain);
        self::assertSame('KVT01-ABCDE', $plain[0]);

        $factor = $this->fakes->factors[self::ADMIN] ?? null;
        self::assertNotNull($factor);
        self::assertSame(self::SECRET, $factor->secret()->toBase32());
        self::assertSame([$this->clock->now(), Totp::stepAt($this->clock->now()), 10], [$factor->enabledAt(), $factor->lastUsedStep(), $factor->remainingBackupCodes()]);
        self::assertSame(
            array_map(static fn (string $code): string => 'h:backup:' . str_replace('-', '', $code), $plain),
            array_map(static fn (BackupCode $code): string => $code->hash(), $factor->backupCodes()),
            'only the digests are kept',
        );
    }

    public function testAWrongCodeEnablesNothing(): void
    {
        try {
            $this->handler()(new EnableSecondFactor(self::ADMIN, self::SECRET, '000000'));
            self::fail('A wrong code was expected to be refused.');
        } catch (InvalidSecondFactorCodeException) {
        }

        self::assertSame([], $this->fakes->factors);
    }

    #[DataProvider('provideOnlyABackOfficeAccountHasASecondFactorCases')]
    public function testOnlyABackOfficeAccountHasASecondFactor(string $userId): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only back-office accounts have a second factor.');

        $this->handler()(new EnableSecondFactor($userId, self::SECRET, '050471'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideOnlyABackOfficeAccountHasASecondFactorCases(): iterable
    {
        yield 'unknown account' => ['01960000-0000-7000-8000-000000003aff'];

        yield 'clinic account' => [self::CLINIC];
    }

    private function handler(): EnableSecondFactorHandler
    {
        return new EnableSecondFactorHandler($this->users, $this->fakes, $this->fakes->backupCodes(), $this->clock);
    }
}
