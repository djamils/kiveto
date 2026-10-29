<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\System\IdentityAccess\Domain\Token\AccountToken;
use App\System\IdentityAccess\Domain\Token\AccountTokenId;
use App\System\IdentityAccess\Domain\Token\AccountTokenRepositoryInterface;
use App\System\IdentityAccess\Domain\Token\TokenPurpose;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\AccountTokenEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalAccountTokenRepositoryTest extends KernelTestCase
{
    private const string USER     = '01960000-0000-7000-8000-00000000ae01';
    private const string OTHER    = '01960000-0000-7000-8000-00000000ae02';
    private const string CLINIC_A = '01960000-0000-7000-8000-00000000ac01';
    private const string CLINIC_B = '01960000-0000-7000-8000-00000000ac02';

    public function testATokenIsFoundByTheHashOfItsSecretAndUsedOnce(): void
    {
        $tokens = $this->tokens();
        $token  = $this->issue('01960000-0000-7000-8000-00000000a001', self::USER, TokenPurpose::INVITATION, self::CLINIC_A, 'secret-1', '2026-10-26 10:00:00');

        self::assertNull($tokens->findByHash(AccountToken::hash('unknown')));

        $found = $tokens->findByHashForUpdate(AccountToken::hash('secret-1'));
        self::assertNotNull($found);
        self::assertSame([$token->id()->toString(), self::USER, TokenPurpose::INVITATION, self::CLINIC_A, '2026-10-29 10:00:00', null, '2026-10-26 10:00:00'], [
            $found->id()->toString(), $found->userId(), $found->purpose(), $found->clinicId(), $found->expiresAt()->format('Y-m-d H:i:s'), $found->usedAt(), $found->createdAt()->format('Y-m-d H:i:s'),
        ]);

        $found->markUsed(new \DateTimeImmutable('2026-10-27 08:00:00'));
        $tokens->save($found);

        self::assertSame('2026-10-27 08:00:00', $tokens->findByHash(AccountToken::hash('secret-1'))?->usedAt()?->format('Y-m-d H:i:s'));
    }

    public function testInvitationsAreScopedToTheClinicAndResetsToTheUser(): void
    {
        $tokens = $this->tokens();
        $this->issue('01960000-0000-7000-8000-00000000a011', self::USER, TokenPurpose::INVITATION, self::CLINIC_A, 's-a', '2026-10-26 10:00:00');
        $this->issue('01960000-0000-7000-8000-00000000a012', self::USER, TokenPurpose::INVITATION, self::CLINIC_B, 's-b', '2026-10-26 10:00:00');
        $this->issue('01960000-0000-7000-8000-00000000a013', self::OTHER, TokenPurpose::INVITATION, self::CLINIC_A, 's-o', '2026-10-20 10:00:00');
        $this->issue('01960000-0000-7000-8000-00000000a014', self::USER, TokenPurpose::PASSWORD_RESET, null, 's-r1', '2026-10-26 10:00:00');
        $this->issue('01960000-0000-7000-8000-00000000a015', self::USER, TokenPurpose::PASSWORD_RESET, null, 's-r2', '2026-10-26 10:05:00');

        $now = new \DateTimeImmutable('2026-10-26 12:00:00');
        self::assertSame([self::USER], $tokens->usersWithUsableInvitation(self::CLINIC_A, [self::USER, self::OTHER], $now), "the other user's invitation expired");
        self::assertSame([], $tokens->usersWithUsableInvitation(self::CLINIC_A, [], $now));

        self::assertSame(1, $tokens->invalidateInvitations(self::USER, self::CLINIC_A, $now));
        self::assertSame([], $tokens->usersWithUsableInvitation(self::CLINIC_A, [self::USER], $now));
        self::assertSame([self::USER], $tokens->usersWithUsableInvitation(self::CLINIC_B, [self::USER], $now), "clinic B's link stays valid");

        self::assertSame(2, $tokens->invalidatePasswordResets(self::USER, $now));
        self::assertNotNull($tokens->findByHash(AccountToken::hash('s-r2'))?->usedAt());
        self::assertNull($tokens->findByHash(AccountToken::hash('s-b'))?->usedAt());
    }

    public function testTheEntityDescribesTheTable(): void
    {
        $this->issue('01960000-0000-7000-8000-00000000a021', self::USER, TokenPurpose::PASSWORD_RESET, null, 's-e', '2026-10-26 10:00:00');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $entity = $em->find(AccountTokenEntity::class, Uuid::fromString('01960000-0000-7000-8000-00000000a021'));

        self::assertInstanceOf(AccountTokenEntity::class, $entity);
        self::assertSame(['PASSWORD_RESET', null, AccountToken::hash('s-e')], [$entity->purpose, $entity->clinicId, $entity->tokenHash]);

        $built = new AccountTokenEntity(Uuid::v7(), Uuid::v7(), 'INVITATION', Uuid::v7(), 'h', new \DateTimeImmutable(), null, new \DateTimeImmutable());
        self::assertSame('INVITATION', $built->purpose);
    }

    private function issue(string $id, string $userId, TokenPurpose $purpose, ?string $clinicId, string $secret, string $at): AccountToken
    {
        $token = AccountToken::issue(AccountTokenId::fromString($id), $userId, $purpose, $clinicId, $secret, new \DateTimeImmutable($at, new \DateTimeZone('UTC')));
        $this->tokens()->save($token);

        return $token;
    }

    private function tokens(): AccountTokenRepositoryInterface
    {
        $tokens = self::getContainer()->get('test.' . AccountTokenRepositoryInterface::class);
        \assert($tokens instanceof AccountTokenRepositoryInterface);

        return $tokens;
    }
}
