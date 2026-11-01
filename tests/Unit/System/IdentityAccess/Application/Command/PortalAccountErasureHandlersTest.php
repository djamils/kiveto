<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command;

use App\Shared\Domain\Idempotency\IdempotencyKey;
use App\Shared\Domain\Idempotency\IdempotencyStoreInterface;
use App\Shared\Domain\Idempotency\RequestFingerprint;
use App\Shared\Domain\Idempotency\StoredResponse;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Application\Command\AnonymisePortalUser\AnonymisePortalUser;
use App\System\IdentityAccess\Application\Command\AnonymisePortalUser\AnonymisePortalUserHandler;
use App\System\IdentityAccess\Application\Command\DisablePortalUser\DisablePortalUser;
use App\System\IdentityAccess\Application\Command\DisablePortalUser\DisablePortalUserHandler;
use App\System\IdentityAccess\Application\Command\PurgePortalPhoneTraces\PurgePortalPhoneTraces;
use App\System\IdentityAccess\Application\Command\PurgePortalPhoneTraces\PurgePortalPhoneTracesHandler;
use App\System\IdentityAccess\Application\Exception\PortalAccountUnavailableException;
use App\System\IdentityAccess\Application\Port\PortalOtpQuotasInterface;
use App\System\IdentityAccess\Domain\Otp\OtpChallenge;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\System\IdentityAccess\Domain\Session\RefreshToken;
use App\System\IdentityAccess\Domain\Session\RefreshTokenFamilyId;
use App\System\IdentityAccess\Domain\Session\RefreshTokenId;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryRefreshTokens;

/** DisablePortalUser (the 202 of the deletion request), then the erasure steps of IdentityAccess. */
final class PortalAccountErasureHandlersTest extends PortalActionOtpTestCase
{
    private InMemoryRefreshTokens $tokens;

    /** @var \ArrayObject<int, string> */
    private \ArrayObject $forgottenPhones;

    /** @var \ArrayObject<int, string> */
    private \ArrayObject $forgottenPrincipals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokens              = new InMemoryRefreshTokens();
        $this->forgottenPhones     = new \ArrayObject();
        $this->forgottenPrincipals = new \ArrayObject();
    }

    public function testDisablingRevokesEveryRefreshTokenOfTheOwner(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE);
        $this->token('01960000-0000-7000-8000-0000000004a1', '01960000-0000-7000-8000-0000000004f1', self::ALICE);
        $this->token('01960000-0000-7000-8000-0000000004a2', '01960000-0000-7000-8000-0000000004f2', self::ALICE);
        $this->token('01960000-0000-7000-8000-0000000004a3', '01960000-0000-7000-8000-0000000004f3', self::BOB);

        new DisablePortalUserHandler($this->owners(), $this->users, $this->tokens, $this->clock())(new DisablePortalUser(self::ALICE));

        self::assertSame(UserStatus::DISABLED, $this->users->users[self::ALICE]->status());
        self::assertSame([self::ALICE], $this->users->locked, 'the account is read locked');
        self::assertSame([true, true, false], array_values(array_map(static fn (RefreshToken $t): bool => $t->isRevoked(), $this->tokens->tokens)));
    }

    public function testAnAccountAlreadyDisabledIsRefused(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE, status: UserStatus::DISABLED);

        $this->expectException(PortalAccountUnavailableException::class);
        new DisablePortalUserHandler($this->owners(), $this->users, $this->tokens, $this->clock())(new DisablePortalUser(self::ALICE));
    }

    public function testThePhoneIsLockedFirstThenItsTracesArePurged(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE, 'alice@example.com', status: UserStatus::DISABLED);
        $this->challenge('01960000-0000-7000-8000-0000000005c1', self::ALICE_PHONE, OtpPurpose::LOGIN, null);
        $this->challenge('01960000-0000-7000-8000-0000000005c2', '+33612340999', OtpPurpose::ACCOUNT_DELETION, self::ALICE);
        $this->challenge('01960000-0000-7000-8000-0000000005c3', self::BOB_PHONE, OtpPurpose::LOGIN, null);

        $purged = $this->purgeHandler()(new PurgePortalPhoneTraces(self::ALICE));

        self::assertSame([self::ALICE_PHONE, 'alice@example.com'], [$purged->phone, $purged->email]);
        self::assertSame([self::ALICE_PHONE], $this->users->lockedPhones);
        self::assertSame([self::ALICE], $this->users->locked, 'the account after its phone');
        self::assertSame(['01960000-0000-7000-8000-0000000005c3'], array_keys($this->challenges->challenges), 'the phone\'s challenges and the user\'s are deleted');
        self::assertSame([self::ALICE_PHONE], $this->forgottenPhones->getArrayCopy());
        self::assertSame([self::ALICE], $this->forgottenPrincipals->getArrayCopy());
    }

    public function testAnAnonymisedAccountHasNoPhoneLeftToPurge(): void
    {
        $this->users->save(User::reconstitute(UserId::fromString(self::ALICE), null, 'hash', $this->now, UserStatus::DISABLED, type: UserType::PORTAL, anonymisedAt: $this->now));

        $purged = $this->purgeHandler()(new PurgePortalPhoneTraces(self::ALICE));

        self::assertSame([null, null], [$purged->phone, $purged->email]);
        self::assertSame([[], []], [$this->users->lockedPhones, $this->forgottenPhones->getArrayCopy()]);
        self::assertSame([self::ALICE], $this->forgottenPrincipals->getArrayCopy(), 'the idempotency keys are deleted anyway');
    }

    public function testOnlyADisabledOwnerAccountIsPurged(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE);

        $this->expectException(\LogicException::class);
        $this->purgeHandler()(new PurgePortalPhoneTraces(self::ALICE));
    }

    public function testAnUnknownAccountIsNeverPurged(): void
    {
        $this->expectException(\LogicException::class);
        $this->purgeHandler()(new PurgePortalPhoneTraces(self::ALICE));
    }

    public function testTheAccountIsAnonymisedAndItsPhoneLockRowDeleted(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE, 'alice@example.com', verified: true, status: UserStatus::DISABLED);
        $handler = new AnonymisePortalUserHandler($this->users, $this->clock());

        $handler(new AnonymisePortalUser(self::ALICE));
        $handler(new AnonymisePortalUser(self::ALICE));

        $user = $this->users->users[self::ALICE];
        self::assertSame([null, null, '2026-11-01 13:05:00'], [$user->phone(), $user->email(), $user->anonymisedAt()?->format('Y-m-d H:i:s')]);
        self::assertSame([self::ALICE_PHONE], $this->users->deletedPhoneLocks, 'once: the retry finds no phone');
    }

    public function testAnUnknownAccountIsNeverAnonymised(): void
    {
        $this->expectException(\LogicException::class);
        new AnonymisePortalUserHandler($this->users, $this->clock())(new AnonymisePortalUser(self::ALICE));
    }

    private function purgeHandler(): PurgePortalPhoneTracesHandler
    {
        $quotas = new class($this->forgottenPhones) implements PortalOtpQuotasInterface {
            /** @param \ArrayObject<int, string> $phones */
            public function __construct(private readonly \ArrayObject $phones)
            {
            }

            public function forgetPhone(string $phone): void
            {
                $this->phones->append($phone);
            }
        };

        $idempotency = new class($this->forgottenPrincipals) implements IdempotencyStoreInterface {
            /** @param \ArrayObject<int, string> $principals */
            public function __construct(private readonly \ArrayObject $principals)
            {
            }

            public function execute(string $principalId, IdempotencyKey $key, RequestFingerprint $fingerprint, \Closure $operation): StoredResponse
            {
                throw new \LogicException('Not used.');
            }

            public function forgetPrincipal(string $principalId): int
            {
                $this->principals->append($principalId);

                return 1;
            }
        };

        return new PurgePortalPhoneTracesHandler($this->users, $this->challenges, $quotas, $idempotency);
    }

    private function token(string $id, string $family, string $userId): void
    {
        $this->tokens->save(RefreshToken::issue(RefreshTokenId::fromString($id), RefreshTokenFamilyId::fromString($family), $userId, hash('sha256', $id), $this->now));
    }

    private function challenge(string $id, string $phone, OtpPurpose $purpose, ?string $subject): void
    {
        $this->challenges->save(OtpChallenge::start(OtpChallengeId::fromString($id), PhoneNumber::fromString($phone), $purpose, $subject, 'digest', $this->now));
    }
}
