<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\System\IdentityAccess\Domain\Session\RefreshToken;
use App\System\IdentityAccess\Domain\Session\RefreshTokenFamilyId;
use App\System\IdentityAccess\Domain\Session\RefreshTokenId;
use App\System\IdentityAccess\Domain\Session\RefreshTokenRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalRefreshTokenRepositoryTest extends KernelTestCase
{
    private const string FIRST    = '01960000-0000-7000-8000-0000000006a1';
    private const string SECOND   = '01960000-0000-7000-8000-0000000006a2';
    private const string GRACE    = '01960000-0000-7000-8000-0000000006a3';
    private const string STRANGER = '01960000-0000-7000-8000-0000000006a4';
    private const string FAMILY   = '01960000-0000-7000-8000-0000000006f1';
    private const string OTHER    = '01960000-0000-7000-8000-0000000006f2';
    private const string USER     = '01960000-0000-7000-8000-0000000006e1';

    public function testAnIssuedTokenIsReadBackByItsDigest(): void
    {
        $tokens = $this->tokens();
        // A Paris time: the dates are stored in UTC and read back as UTC.
        $tokens->save($this->issue(self::FIRST, self::FAMILY, 's1', new \DateTimeImmutable('2026-10-26 08:14:00', new \DateTimeZone('Europe/Paris'))));

        self::assertNull($tokens->findByHashForUpdate(hash('sha256', 'unknown')));
        self::assertNull($tokens->findById(RefreshTokenId::fromString(self::STRANGER)));

        $found = $this->em()->wrapInTransaction(static fn (): ?RefreshToken => $tokens->findByHashForUpdate(hash('sha256', 's1')));
        self::assertNotNull($found);
        self::assertSame([self::FIRST, self::FAMILY, self::USER, hash('sha256', 's1'), '2026-10-26 07:14:00', '2027-01-24 07:14:00', 'UTC'], [
            $found->id()->toString(), $found->familyId()->toString(), $found->userId(), $found->tokenHash(),
            $found->createdAt()->format('Y-m-d H:i:s'), $found->expiresAt()->format('Y-m-d H:i:s'), $found->expiresAt()->getTimezone()->getName(),
        ]);
        self::assertSame([null, null, null, null, null], [$found->lastUsedAt(), $found->rotatedAt(), $found->rotatedToId(), $found->graceSuccessorId(), $found->revokedAt()]);
    }

    public function testTheRotationAndGraceSuccessorsAreStoredAndTheFamilyRevokedTogether(): void
    {
        $tokens = $this->tokens();
        $now    = new \DateTimeImmutable('2026-10-26 07:14:00', new \DateTimeZone('UTC'));
        $first  = $this->issue(self::FIRST, self::FAMILY, 's1', $now);
        $tokens->save($first);
        $tokens->save($this->issue(self::STRANGER, self::OTHER, 's4', $now));

        $tokens->save($first->rotate(RefreshTokenId::fromString(self::SECOND), hash('sha256', 's2'), $now->modify('+10 seconds')));
        $tokens->save($first->reissue(RefreshTokenId::fromString(self::GRACE), hash('sha256', 's3'), $now->modify('+20 seconds')));
        $tokens->save($first);

        $found = $tokens->findById(RefreshTokenId::fromString(self::FIRST));
        self::assertNotNull($found);
        self::assertSame(['07:14:20', '07:14:10', self::SECOND, self::GRACE, null], [
            $found->lastUsedAt()?->format('H:i:s'), $found->rotatedAt()?->format('H:i:s'), $found->rotatedToId()?->toString(), $found->graceSuccessorId()?->toString(), $found->revokedAt(),
        ]);
        self::assertSame([self::FAMILY, '07:14:10'], [$tokens->findById(RefreshTokenId::fromString(self::SECOND))?->familyId()->toString(), $tokens->findById(RefreshTokenId::fromString(self::SECOND))?->createdAt()->format('H:i:s')]);

        self::assertSame(3, $tokens->revokeFamily(RefreshTokenFamilyId::fromString(self::FAMILY), $now->modify('+1 minute')));
        self::assertSame(0, $tokens->revokeFamily(RefreshTokenFamilyId::fromString(self::FAMILY), $now->modify('+2 minutes')), 'already revoked');

        foreach ([self::FIRST, self::SECOND, self::GRACE] as $id) {
            self::assertSame('07:15:00', $tokens->findById(RefreshTokenId::fromString($id))?->revokedAt()?->format('H:i:s'), $id);
        }

        self::assertFalse($tokens->findById(RefreshTokenId::fromString(self::STRANGER))?->isRevoked(), 'another family is untouched');
    }

    private function issue(string $id, string $family, string $secret, \DateTimeImmutable $at): RefreshToken
    {
        return RefreshToken::issue(RefreshTokenId::fromString($id), RefreshTokenFamilyId::fromString($family), self::USER, hash('sha256', $secret), $at);
    }

    private function tokens(): RefreshTokenRepositoryInterface
    {
        $repository = self::getContainer()->get(RefreshTokenRepositoryInterface::class);
        \assert($repository instanceof RefreshTokenRepositoryInterface);

        return $repository;
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
