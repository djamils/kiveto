<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\AccountDeletion;

use App\Context\ClientPortal\Application\Command\RequestAccountDeletion\RequestAccountDeletion;
use App\Context\ClientPortal\Application\Command\RequestAccountDeletion\RequestAccountDeletionHandler;
use App\Context\ClientPortal\Application\Port\AccountDeletionJobsInterface;
use App\Context\ClientPortal\Application\Port\DeletionCodeCheck;
use App\Context\ClientPortal\Application\Port\ErasedOwner;
use App\Context\ClientPortal\Application\Port\PortalAccountErasureInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** AC 64, 65: only the right `account_deletion` code disables the account and queues its erasure. */
final class RequestAccountDeletionHandlerTest extends TestCase
{
    /** @var \ArrayObject<int, string> */
    private \ArrayObject $calls;

    protected function setUp(): void
    {
        $this->calls = new \ArrayObject();
    }

    public function testTheRightCodeDisablesTheAccountThenQueuesItsErasure(): void
    {
        $check = $this->handler(DeletionCodeCheck::VERIFIED)(new RequestAccountDeletion('user-1', 'challenge-1', '123456'));

        self::assertSame(DeletionCodeCheck::VERIFIED, $check);
        self::assertSame(['verify:user-1:challenge-1:123456', 'disable:user-1', 'queue:user-1'], $this->calls->getArrayCopy());
    }

    /**
     * @return iterable<string, array{DeletionCodeCheck}>
     */
    public static function provideAWrongCodeChangesNothingCases(): iterable
    {
        yield 'invalid' => [DeletionCodeCheck::OTP_INVALID];
        yield 'expired' => [DeletionCodeCheck::OTP_EXPIRED];
        yield 'attempts' => [DeletionCodeCheck::OTP_ATTEMPTS_EXCEEDED];
    }

    #[DataProvider('provideAWrongCodeChangesNothingCases')]
    public function testAWrongCodeChangesNothing(DeletionCodeCheck $outcome): void
    {
        self::assertSame($outcome, $this->handler($outcome)(new RequestAccountDeletion('user-1', 'challenge-1', '000000')));
        self::assertSame(['verify:user-1:challenge-1:000000'], $this->calls->getArrayCopy());
    }

    private function handler(DeletionCodeCheck $outcome): RequestAccountDeletionHandler
    {
        $accounts = new class($this->calls, $outcome) implements PortalAccountErasureInterface {
            /** @param \ArrayObject<int, string> $calls */
            public function __construct(private readonly \ArrayObject $calls, private readonly DeletionCodeCheck $outcome)
            {
            }

            public function verifyDeletionCode(string $userId, string $challengeId, string $code): DeletionCodeCheck
            {
                $this->calls->append('verify:' . $userId . ':' . $challengeId . ':' . $code);

                return $this->outcome;
            }

            public function disable(string $userId): void
            {
                $this->calls->append('disable:' . $userId);
            }

            public function purgeTraces(string $userId): ErasedOwner
            {
                throw new \LogicException('Not used.');
            }

            public function anonymise(string $userId): void
            {
                throw new \LogicException('Not used.');
            }
        };
        $jobs = new class($this->calls) implements AccountDeletionJobsInterface {
            /** @param \ArrayObject<int, string> $calls */
            public function __construct(private readonly \ArrayObject $calls)
            {
            }

            public function queueDeletion(string $userId): void
            {
                $this->calls->append('queue:' . $userId);
            }
        };

        return new RequestAccountDeletionHandler($accounts, $jobs);
    }
}
