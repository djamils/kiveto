<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Pipeline;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Security\OpaqueTokenHasherInterface;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Application\Command\IssuePortalSession\IssuePortalSession;
use App\System\IdentityAccess\Application\Command\IssuePortalSession\PortalSessionTokens;
use App\System\IdentityAccess\Application\Command\RefreshPortalSession\RefreshPortalSession;
use App\System\IdentityAccess\Application\Command\RefreshPortalSession\RefreshPortalSessionHandler;
use App\System\IdentityAccess\Application\Command\RefreshPortalSession\RefreshResult;
use App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp\LoginOtpOutcome;
use App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp\LoginOtpResult;
use App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp\VerifyPortalLoginOtp;
use App\System\IdentityAccess\Application\Port\AccessTokenIssuerInterface;
use App\System\IdentityAccess\Application\Port\OtpCodeHasherInterface;
use App\System\IdentityAccess\Application\Service\PortalSessionIssuer;
use App\System\IdentityAccess\Domain\Otp\OtpChallenge;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeRepositoryInterface;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\Session\RefreshOutcome;
use App\System\IdentityAccess\Domain\Session\RefreshToken;
use App\System\IdentityAccess\Domain\Session\RefreshTokenFamilyId;
use App\System\IdentityAccess\Domain\Session\RefreshTokenId;
use App\System\IdentityAccess\Domain\Session\RefreshTokenRepositoryInterface;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\Tests\Shared\Time\FrozenClock;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The refresh rules of D5 on the real tables (FrozenClock, hand-built
 * handler), and the OTP / refresh failures that must be committed through
 * the real command bus (AC 40, AC 41).
 */
final class PortalSessionPipelineTest extends KernelTestCase
{
    private const string OWNER    = '01960000-0000-7000-8000-0000000008e1';
    private const string DISABLED = '01960000-0000-7000-8000-0000000008e2';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-10-26 07:14:00', new \DateTimeZone('UTC'));
        $this->users()->save(User::registerPortal(UserId::fromString(self::OWNER), PhoneNumber::fromString('+33612340801'), null, $this->now));
    }

    /**
     * AC 14, AC 15, AC 48: a rotation, one grace pair, then a third
     * presentation revokes the whole family, grace pair included.
     */
    public function testTwoRefreshesWithinThirtySecondsAreHonouredAndAThirdRevokesTheFamily(): void
    {
        $first = $this->open(self::OWNER);

        $rotated = $this->refresh($first, '+5 seconds');
        $grace   = $this->refresh($first, '+20 seconds');
        self::assertSame([RefreshOutcome::ROTATED, RefreshOutcome::GRACE_REISSUED], [$rotated->outcome, $grace->outcome]);
        $rotatedPair = $rotated->tokens;
        $gracePair   = $grace->tokens;
        self::assertNotNull($rotatedPair);
        self::assertNotNull($gracePair);
        self::assertNotSame($rotatedPair->refreshToken, $gracePair->refreshToken);

        $row = $this->row($first);
        self::assertSame([$this->idOf($rotatedPair), $this->idOf($gracePair), null], [$row['rotated_to_id'], $row['grace_successor_id'], $row['revoked_at']]);
        self::assertSame([0, 3], [$this->revokedCount($first), $this->familySize($first)], 'two valid pairs, family alive');

        $replay = $this->refresh($first, '+25 seconds');
        self::assertSame([RefreshOutcome::REUSE_DETECTED, null], [$replay->outcome, $replay->tokens]);
        self::assertSame(3, $this->revokedCount($first));
        self::assertSame(RefreshOutcome::REVOKED, $this->refresh($gracePair, '+26 seconds')->outcome);
    }

    /** AC 16, AC 17. */
    public function testAReplayAfterThirtySecondsOrAfterTheSuccessorWasUsedRevokesEveryToken(): void
    {
        $late = $this->open(self::OWNER);
        $this->refresh($late, '+0 seconds');
        self::assertSame(RefreshOutcome::REUSE_DETECTED, $this->refresh($late, '+31 seconds')->outcome);
        self::assertSame([2, 2], [$this->revokedCount($late), $this->familySize($late)]);

        $used = $this->open(self::OWNER);
        $next = $this->refresh($used, '+0 seconds')->tokens;
        self::assertNotNull($next);
        self::assertSame(RefreshOutcome::ROTATED, $this->refresh($next, '+5 seconds')->outcome);
        self::assertSame(RefreshOutcome::REUSE_DETECTED, $this->refresh($used, '+10 seconds')->outcome);
        self::assertSame([3, 3], [$this->revokedCount($used), $this->familySize($used)]);
    }

    /** AC 49, AC 19. */
    public function testADisabledOwnerOrAnUnusedTokenEndsTheSession(): void
    {
        $this->users()->save(User::reconstitute(UserId::fromString(self::DISABLED), null, 'hash', $this->now, UserStatus::DISABLED, null, UserType::PORTAL, PhoneNumber::fromString('+33612340802'), $this->now));
        $disabled = $this->open(self::DISABLED);
        self::assertSame(RefreshOutcome::USER_INACTIVE, $this->refresh($disabled, '+1 minute')->outcome);
        self::assertSame(1, $this->revokedCount($disabled));

        $idle = $this->open(self::OWNER);
        self::assertSame(RefreshOutcome::EXPIRED, $this->refresh($idle, '+90 days')->outcome);
    }

    /** AC 18: only digests are stored, in the tokens table and the limiter cache. */
    public function testNoClearRefreshTokenIsStored(): void
    {
        $first  = $this->open(self::OWNER);
        $second = $this->refresh($first, '+5 seconds')->tokens;
        $third  = $this->refresh($first, '+10 seconds')->tokens;
        self::assertNotNull($second);
        self::assertNotNull($third);

        foreach ([$first->refreshToken, $second->refreshToken, $third->refreshToken] as $secret) {
            self::assertSame(0, $this->number('SELECT COUNT(*) FROM identity_access__refresh_tokens WHERE token_hash LIKE ? OR id LIKE ? OR family_id LIKE ?', array_fill(0, 3, '%' . $secret . '%')));
            self::assertSame(0, $this->number('SELECT COUNT(*) FROM shared__rate_limiter_cache WHERE item_id LIKE ? OR item_data LIKE ?', array_fill(0, 2, '%' . $secret . '%')));
            self::assertSame(1, $this->number('SELECT COUNT(*) FROM identity_access__refresh_tokens WHERE token_hash = ?', [hash('sha256', $secret)]));
        }
    }

    /** AC 40: every wrong attempt is committed by the real bus, then the right code is refused. */
    public function testFiveWrongCodesThroughTheBusExhaustTheChallenge(): void
    {
        $hasher = self::getContainer()->get(OtpCodeHasherInterface::class);
        \assert($hasher instanceof OtpCodeHasherInterface);
        $challenges = self::getContainer()->get(OtpChallengeRepositoryInterface::class);
        \assert($challenges instanceof OtpChallengeRepositoryInterface);
        $id = '01960000-0000-7000-8000-0000000008c1';
        $challenges->save(OtpChallenge::start(OtpChallengeId::fromString($id), PhoneNumber::fromString('+33612340801'), OtpPurpose::LOGIN, null, $hasher->hash('042137'), new \DateTimeImmutable()));

        for ($i = 0; $i < 5; ++$i) {
            $this->bus()->dispatch(new VerifyPortalLoginOtp($id, '000000'));
        }

        $outcome = $this->bus()->dispatch(new VerifyPortalLoginOtp($id, '042137'));
        self::assertInstanceOf(LoginOtpOutcome::class, $outcome);
        self::assertSame(LoginOtpResult::OTP_ATTEMPTS_EXCEEDED, $outcome->result);
        self::assertSame(5, $this->number('SELECT attempts FROM identity_access__otp_challenges WHERE id = ?', [Uuid::fromString($id)->toBinary()]));
    }

    /** AC 41: a token rotated 31 s ago, replayed through the real bus. */
    public function testALateReplayThroughTheBusRevokesTheFamilyInTheDatabase(): void
    {
        $pair = $this->bus()->dispatch(new IssuePortalSession(self::OWNER));
        self::assertInstanceOf(PortalSessionTokens::class, $pair);
        $tokens = $this->refreshTokens();
        $token  = $this->stored($pair);
        $past   = new \DateTimeImmutable('-31 seconds');
        // Rewind the session: issued and rotated 31 s ago.
        $rewound = RefreshToken::issue($token->id(), $token->familyId(), self::OWNER, $token->tokenHash(), $past);
        $tokens->save($rewound->rotate(RefreshTokenId::fromString('01960000-0000-7000-8000-0000000008a1'), hash('sha256', 'successor'), $past));
        $tokens->save($rewound);

        $result = $this->bus()->dispatch(new RefreshPortalSession($pair->refreshToken));
        self::assertInstanceOf(RefreshResult::class, $result);
        self::assertSame(RefreshOutcome::REUSE_DETECTED, $result->outcome);
        self::assertSame([2, 2], [$this->revokedCount($pair), $this->familySize($pair)]);
    }

    private function open(string $userId): PortalSessionTokens
    {
        return $this->issuer()->open($userId, $this->now);
    }

    private function refresh(PortalSessionTokens $pair, string $after): RefreshResult
    {
        $hasher = self::getContainer()->get(OpaqueTokenHasherInterface::class);
        \assert($hasher instanceof OpaqueTokenHasherInterface);
        $handler = new RefreshPortalSessionHandler($this->refreshTokens(), $this->users(), $this->issuer(), $hasher, new FrozenClock($this->now->modify($after)));

        return $handler(new RefreshPortalSession($pair->refreshToken));
    }

    private function issuer(): PortalSessionIssuer
    {
        $container = self::getContainer();
        $access    = $container->get(AccessTokenIssuerInterface::class);
        $hasher    = $container->get(OpaqueTokenHasherInterface::class);
        $uuids     = $container->get(UuidGeneratorInterface::class);
        \assert($access instanceof AccessTokenIssuerInterface && $hasher instanceof OpaqueTokenHasherInterface && $uuids instanceof UuidGeneratorInterface);

        return new PortalSessionIssuer($this->refreshTokens(), $access, $hasher, $uuids);
    }

    private function stored(PortalSessionTokens $pair): RefreshToken
    {
        $token = $this->refreshTokens()->findByHashForUpdate(hash('sha256', $pair->refreshToken));
        self::assertNotNull($token);

        return $token;
    }

    private function familyOf(PortalSessionTokens $pair): RefreshTokenFamilyId
    {
        return $this->stored($pair)->familyId();
    }

    private function idOf(PortalSessionTokens $pair): string
    {
        return Uuid::fromString($this->stored($pair)->id()->toString())->toBinary();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(PortalSessionTokens $pair): array
    {
        $row = $this->connection()->fetchAssociative('SELECT * FROM identity_access__refresh_tokens WHERE token_hash = ?', [hash('sha256', $pair->refreshToken)]);
        self::assertIsArray($row);

        return $row;
    }

    private function revokedCount(PortalSessionTokens $pair): int
    {
        return $this->number('SELECT COUNT(*) FROM identity_access__refresh_tokens WHERE family_id = ? AND revoked_at IS NOT NULL', [$this->familyBinary($pair)]);
    }

    private function familySize(PortalSessionTokens $pair): int
    {
        return $this->number('SELECT COUNT(*) FROM identity_access__refresh_tokens WHERE family_id = ?', [$this->familyBinary($pair)]);
    }

    private function familyBinary(PortalSessionTokens $pair): string
    {
        return Uuid::fromString($this->familyOf($pair)->toString())->toBinary();
    }

    /**
     * @param list<string> $parameters
     */
    private function number(string $sql, array $parameters): int
    {
        $value = $this->connection()->fetchOne($sql, $parameters);
        \assert(is_numeric($value));

        return (int) $value;
    }

    private function bus(): CommandBusInterface
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }

    private function users(): UserRepositoryInterface
    {
        $users = self::getContainer()->get(UserRepositoryInterface::class);
        \assert($users instanceof UserRepositoryInterface);

        return $users;
    }

    private function refreshTokens(): RefreshTokenRepositoryInterface
    {
        $tokens = self::getContainer()->get(RefreshTokenRepositoryInterface::class);
        \assert($tokens instanceof RefreshTokenRepositoryInterface);

        return $tokens;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);

        return $connection;
    }
}
