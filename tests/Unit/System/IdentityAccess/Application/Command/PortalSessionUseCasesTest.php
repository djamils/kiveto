<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Infrastructure\Security\OpaqueTokenHasher;
use App\System\IdentityAccess\Application\Command\IssuePortalSession\IssuePortalSession;
use App\System\IdentityAccess\Application\Command\IssuePortalSession\IssuePortalSessionHandler;
use App\System\IdentityAccess\Application\Command\IssuePortalSession\PortalSessionTokens;
use App\System\IdentityAccess\Application\Command\RefreshPortalSession\RefreshPortalSession;
use App\System\IdentityAccess\Application\Command\RefreshPortalSession\RefreshPortalSessionHandler;
use App\System\IdentityAccess\Application\Command\RefreshPortalSession\RefreshResult;
use App\System\IdentityAccess\Application\Command\RevokePortalSession\RevokePortalSession;
use App\System\IdentityAccess\Application\Command\RevokePortalSession\RevokePortalSessionHandler;
use App\System\IdentityAccess\Application\Service\PortalSessionIssuer;
use App\System\IdentityAccess\Domain\Session\RefreshOutcome;
use App\System\IdentityAccess\Domain\Session\RefreshToken;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\AccountFakes;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryRefreshTokens;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryUsers;
use App\Tests\Unit\System\IdentityAccess\Support\PortalFakes;
use PHPUnit\Framework\TestCase;

/**
 * Issuance, refresh (the rules of D5, in order) and logout of Portal
 * sessions on in-memory doubles.
 */
final class PortalSessionUseCasesTest extends TestCase
{
    private const string OWNER = '01960000-0000-7000-8000-0000000003e1';
    private const string OTHER = '01960000-0000-7000-8000-0000000003e2';

    private InMemoryRefreshTokens $tokens;
    private InMemoryUsers $users;
    private PortalFakes $portal;
    private OpaqueTokenHasher $hasher;
    private PortalSessionIssuer $issuer;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->tokens = new InMemoryRefreshTokens();
        $this->users  = new InMemoryUsers();
        $this->portal = new PortalFakes();
        $this->hasher = new OpaqueTokenHasher();
        $this->issuer = new PortalSessionIssuer($this->tokens, $this->portal, $this->hasher, new AccountFakes());
        $this->now    = new \DateTimeImmutable('2026-10-26 07:14:00', new \DateTimeZone('UTC'));

        $this->users->save($this->portalUser(self::OWNER, UserStatus::ACTIVE));
    }

    public function testASessionOpensANewFamilyAndStoresOnlyTheDigestOfTheSecret(): void
    {
        $pair  = $this->open();
        $token = $this->stored($pair);

        self::assertSame(['jwt.' . self::OWNER . '.1', '07:29:00', '2027-01-24 07:14:00'], [$pair->accessToken, $pair->accessTokenExpiresAt->format('H:i:s'), $pair->refreshTokenExpiresAt->format('Y-m-d H:i:s')]);
        self::assertSame([self::OWNER, $this->hasher->hash($pair->refreshToken), false], [$token->userId(), $token->tokenHash(), $token->isUsed()]);
        self::assertNotSame($pair->refreshToken, $token->tokenHash());
        self::assertNotSame($token->familyId()->toString(), $this->stored($this->open())->familyId()->toString(), 'each sign-in is a new family');
    }

    public function testAnUnusedTokenIsRotatedIntoTheSameFamily(): void
    {
        $first  = $this->open();
        $result = $this->refresh($first, '+1 hour');
        $old    = $this->stored($first);

        self::assertSame(RefreshOutcome::ROTATED, $result->outcome);
        $next = $this->stored($this->tokensOf($result));
        self::assertSame([true, '08:14:00', $next->id()->toString()], [$old->isRotated(), $old->rotatedAt()?->format('H:i:s'), $old->rotatedToId()?->toString()]);
        self::assertTrue($next->familyId()->equals($old->familyId()));
        self::assertSame([self::OWNER, self::OWNER], $this->portal->accessTokensFor);
        self::assertSame([$this->hasher->hash($first->refreshToken)], $this->tokens->locked);
    }

    public function testAReplayWithinThirtySecondsYieldsOneMorePairThenRevokesTheFamily(): void
    {
        $first = $this->open();
        $this->refresh($first, '+0 seconds');

        $grace = $this->refresh($first, '+20 seconds');
        self::assertSame(RefreshOutcome::GRACE_REISSUED, $grace->outcome);
        $gracePair = $this->tokensOf($grace);
        self::assertSame($this->stored($gracePair)->id()->toString(), $this->stored($first)->graceSuccessorId()?->toString());
        self::assertCount(3, $this->tokens->family($this->stored($first)->familyId()));

        self::assertSame([RefreshOutcome::REUSE_DETECTED, null], $this->outcome($this->refresh($first, '+25 seconds')), 'a second replay');
        $this->assertFamilyRevoked($first, 3);
        self::assertSame(RefreshOutcome::REVOKED, $this->refresh($gracePair, '+26 seconds')->outcome, 'the grace pair is revoked too');
    }

    public function testAReplayAfterThirtySecondsRevokesTheFamily(): void
    {
        $first = $this->open();
        $next  = $this->tokensOf($this->refresh($first, '+0 seconds'));

        self::assertSame(RefreshOutcome::REUSE_DETECTED, $this->refresh($first, '+31 seconds')->outcome);
        $this->assertFamilyRevoked($first, 2);
        self::assertSame(RefreshOutcome::REVOKED, $this->refresh($next, '+32 seconds')->outcome);
    }

    public function testAReplayAfterTheSuccessorWasUsedRevokesTheFamily(): void
    {
        $first = $this->open();
        $next  = $this->tokensOf($this->refresh($first, '+0 seconds'));
        self::assertSame(RefreshOutcome::ROTATED, $this->refresh($next, '+5 seconds')->outcome);

        self::assertSame(RefreshOutcome::REUSE_DETECTED, $this->refresh($first, '+10 seconds')->outcome);
        $this->assertFamilyRevoked($first, 3);
    }

    public function testAReplayWhoseSuccessorIsGoneIsTreatedAsTheft(): void
    {
        $first     = $this->open();
        $next      = $this->tokensOf($this->refresh($first, '+0 seconds'));
        $successor = $this->stored($next);
        unset($this->tokens->tokens[$successor->id()->toString()]);

        self::assertSame(RefreshOutcome::REUSE_DETECTED, $this->refresh($first, '+5 seconds')->outcome);
    }

    public function testUnknownRevokedAndExpiredTokensAreRefusedWithoutTouchingTheFamily(): void
    {
        self::assertSame([RefreshOutcome::UNKNOWN, null], $this->outcome($this->refresh(new PortalSessionTokens('a', $this->now, 'unknown-secret', $this->now), '+0 seconds')));

        $expired = $this->open();
        self::assertSame(RefreshOutcome::EXPIRED, $this->refresh($expired, '+90 days')->outcome);
        self::assertFalse($this->stored($expired)->isRevoked(), 'an expired token does not revoke its family');
        self::assertSame(RefreshOutcome::ROTATED, $this->refresh($expired, '+90 days -1 second')->outcome, 'used the second before: still valid');

        $revoked = $this->open();
        $this->tokens->revokeFamily($this->stored($revoked)->familyId(), $this->now);
        self::assertSame([RefreshOutcome::REVOKED, null], $this->outcome($this->refresh($revoked, '+1 minute')));
    }

    public function testAnAccountNoLongerActiveRevokesItsFamily(): void
    {
        foreach ([UserStatus::DISABLED, UserStatus::PENDING] as $status) {
            $this->users->save($this->portalUser(self::OWNER, $status));
            $pair = $this->open();

            self::assertSame([RefreshOutcome::USER_INACTIVE, null], $this->outcome($this->refresh($pair, '+1 minute')), $status->value);
            $this->assertFamilyRevoked($pair, 1);
        }

        $this->users->save(User::register(UserId::fromString(self::OWNER), 'vet@example.com', 'hash', $this->now, UserType::CLINIC));
        $staff = $this->open();
        self::assertSame(RefreshOutcome::USER_INACTIVE, $this->refresh($staff, '+1 minute')->outcome, 'not a PORTAL account');

        $this->users->users = [];
        $gone               = $this->open();
        self::assertSame(RefreshOutcome::USER_INACTIVE, $this->refresh($gone, '+1 minute')->outcome, 'no account');
        $this->assertFamilyRevoked($gone, 1);
    }

    public function testLogoutRevokesTheCallersFamilyOnly(): void
    {
        $this->users->save($this->portalUser(self::OTHER, UserStatus::ACTIVE));
        $mine   = $this->open();
        $theirs = (new IssuePortalSessionHandler($this->issuer, new FrozenClock($this->now)))(new IssuePortalSession(self::OTHER));
        $this->tokensOf($this->refresh($mine, '+1 minute'));

        $this->logout($theirs->refreshToken, self::OWNER);
        $this->logout('unknown-secret', self::OWNER);
        self::assertFalse($this->stored($theirs)->isRevoked(), "another user's family is untouched");

        $this->logout($mine->refreshToken, self::OWNER);
        $this->assertFamilyRevoked($mine, 2);
        self::assertSame(RefreshOutcome::REVOKED, $this->refresh($mine, '+2 minutes')->outcome);
        self::assertSame(RefreshOutcome::ROTATED, $this->refresh($theirs, '+2 minutes')->outcome);
    }

    private function assertFamilyRevoked(PortalSessionTokens $pair, int $count): void
    {
        $family = $this->tokens->family($this->stored($pair)->familyId());

        self::assertCount($count, $family);
        self::assertSame(array_fill(0, $count, true), array_map(static fn (RefreshToken $t): bool => $t->isRevoked(), $family));
    }

    private function open(): PortalSessionTokens
    {
        return (new IssuePortalSessionHandler($this->issuer, new FrozenClock($this->now)))(new IssuePortalSession(self::OWNER));
    }

    private function refresh(PortalSessionTokens $pair, string $after): RefreshResult
    {
        $handler = new RefreshPortalSessionHandler($this->tokens, $this->users, $this->issuer, $this->hasher, new FrozenClock($this->now->modify($after)));

        return $handler(new RefreshPortalSession($pair->refreshToken));
    }

    private function logout(string $refreshToken, string $userId): void
    {
        (new RevokePortalSessionHandler($this->tokens, $this->hasher, new FrozenClock($this->now->modify('+90 seconds'))))(new RevokePortalSession($refreshToken, $userId));
    }

    private function tokensOf(RefreshResult $result): PortalSessionTokens
    {
        self::assertTrue($result->outcome->issuesPair());
        self::assertNotNull($result->tokens);

        return $result->tokens;
    }

    /**
     * @return array{RefreshOutcome, PortalSessionTokens|null}
     */
    private function outcome(RefreshResult $result): array
    {
        return [$result->outcome, $result->tokens];
    }

    private function stored(PortalSessionTokens $pair): RefreshToken
    {
        $hash = $this->hasher->hash($pair->refreshToken);

        foreach ($this->tokens->tokens as $token) {
            if ($token->tokenHash() === $hash) {
                return $token;
            }
        }

        self::fail('No stored token for this pair.');
    }

    private function portalUser(string $id, UserStatus $status): User
    {
        return User::reconstitute(UserId::fromString($id), null, 'hash', $this->now, $status, null, UserType::PORTAL, PhoneNumber::fromString('+33612345678'), $this->now);
    }
}
