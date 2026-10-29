<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain\Session;

use App\System\IdentityAccess\Domain\Session\RefreshOutcome;
use App\System\IdentityAccess\Domain\Session\RefreshToken;
use App\System\IdentityAccess\Domain\Session\RefreshTokenFamilyId;
use App\System\IdentityAccess\Domain\Session\RefreshTokenId;
use PHPUnit\Framework\TestCase;

/**
 * D5: rotated once, re-issued once within 30 s while the successor is
 * unused, expired after 90 days.
 */
final class RefreshTokenTest extends TestCase
{
    private const string ID        = '01960000-0000-7000-8000-0000000001a1';
    private const string SUCCESSOR = '01960000-0000-7000-8000-0000000001a2';
    private const string GRACE     = '01960000-0000-7000-8000-0000000001a3';
    private const string FAMILY    = '01960000-0000-7000-8000-0000000001f1';
    private const string USER      = '01960000-0000-7000-8000-0000000001e1';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-10-26 07:14:00', new \DateTimeZone('UTC'));
    }

    public function testAnIssuedTokenIsUnusedAndLivesNinetyDays(): void
    {
        $token = $this->issue();

        self::assertSame([self::ID, self::FAMILY, self::USER, hash('sha256', 's1')], [$token->id()->toString(), $token->familyId()->toString(), $token->userId(), $token->tokenHash()]);
        self::assertSame(['2026-10-26 07:14:00', '2027-01-24 07:14:00'], [$token->createdAt()->format('Y-m-d H:i:s'), $token->expiresAt()->format('Y-m-d H:i:s')]);
        self::assertSame([null, null, null, null, null], [$token->lastUsedAt(), $token->rotatedAt(), $token->rotatedToId(), $token->graceSuccessorId(), $token->revokedAt()]);
        self::assertSame([false, false, false], [$token->isUsed(), $token->isRotated(), $token->isRevoked()]);
        self::assertFalse($token->isExpired($this->now->modify('+90 days -1 second')));
        self::assertTrue($token->isExpired($this->now->modify('+90 days')));
    }

    public function testATokenIsRotatedOnceIntoASuccessorOfTheSameFamily(): void
    {
        $token     = $this->issue();
        $later     = $this->now->modify('+5 minutes');
        $successor = $token->rotate(RefreshTokenId::fromString(self::SUCCESSOR), hash('sha256', 's2'), $later);

        self::assertSame([true, true, '07:19:00', '07:19:00', self::SUCCESSOR], [
            $token->isRotated(), $token->isUsed(), $token->rotatedAt()?->format('H:i:s'), $token->lastUsedAt()?->format('H:i:s'), $token->rotatedToId()?->toString(),
        ]);
        self::assertSame([self::SUCCESSOR, self::FAMILY, self::USER, hash('sha256', 's2'), false, '2027-01-24 07:19:00'], [
            $successor->id()->toString(), $successor->familyId()->toString(), $successor->userId(), $successor->tokenHash(), $successor->isUsed(), $successor->expiresAt()->format('Y-m-d H:i:s'),
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A refresh token is rotated once.');
        $token->rotate(RefreshTokenId::fromString(self::GRACE), hash('sha256', 's3'), $later);
    }

    public function testARotatedTokenCanBeReissuedOnceWithinThirtySecondsWhileItsSuccessorIsUnused(): void
    {
        $token = $this->issue();
        self::assertFalse($token->canBeReissued($this->now, false), 'never rotated');

        $token->rotate(RefreshTokenId::fromString(self::SUCCESSOR), hash('sha256', 's2'), $this->now);

        self::assertTrue($token->canBeReissued($this->now->modify('+29 seconds'), false));
        self::assertFalse($token->canBeReissued($this->now->modify('+30 seconds'), false), 'the window is closed at 30 s');
        self::assertFalse($token->canBeReissued($this->now->modify('+10 seconds'), true), 'the successor was already used');

        $grace = $token->reissue(RefreshTokenId::fromString(self::GRACE), hash('sha256', 's3'), $this->now->modify('+10 seconds'));

        self::assertSame([self::GRACE, self::FAMILY, self::USER, '07:14:10'], [$grace->id()->toString(), $grace->familyId()->toString(), $grace->userId(), $grace->createdAt()->format('H:i:s')]);
        self::assertSame([self::GRACE, '07:14:10', self::SUCCESSOR], [$token->graceSuccessorId()?->toString(), $token->lastUsedAt()?->format('H:i:s'), $token->rotatedToId()?->toString()]);
        self::assertFalse($token->canBeReissued($this->now->modify('+11 seconds'), false), 'only one grace pair');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A refresh token is re-issued once.');
        $token->reissue(RefreshTokenId::fromString('01960000-0000-7000-8000-0000000001a4'), hash('sha256', 's4'), $this->now);
    }

    public function testAReconstitutedTokenKeepsItsState(): void
    {
        $at    = $this->now;
        $token = RefreshToken::reconstitute(
            RefreshTokenId::fromString(self::ID),
            RefreshTokenFamilyId::fromString(self::FAMILY),
            self::USER,
            hash('sha256', 's1'),
            $at,
            $at,
            $at->modify('+90 days'),
            $at,
            RefreshTokenId::fromString(self::SUCCESSOR),
            RefreshTokenId::fromString(self::GRACE),
            $at,
        );

        self::assertSame([true, true, true, self::SUCCESSOR, self::GRACE], [$token->isRevoked(), $token->isRotated(), $token->isUsed(), $token->rotatedToId()?->toString(), $token->graceSuccessorId()?->toString()]);
    }

    public function testOnlyARotationOrAGraceReissueHandsOutAPair(): void
    {
        $issuing = array_map(static fn (RefreshOutcome $o): string => $o->value, array_values(array_filter(RefreshOutcome::cases(), static fn (RefreshOutcome $o): bool => $o->issuesPair())));

        self::assertSame(['rotated', 'grace_reissued'], $issuing);
        self::assertCount(7, RefreshOutcome::cases());
    }

    private function issue(): RefreshToken
    {
        return RefreshToken::issue(RefreshTokenId::fromString(self::ID), RefreshTokenFamilyId::fromString(self::FAMILY), self::USER, hash('sha256', 's1'), $this->now);
    }
}
