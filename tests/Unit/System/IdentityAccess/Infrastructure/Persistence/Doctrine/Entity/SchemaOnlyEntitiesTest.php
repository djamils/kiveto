<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity;

use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\OtpChallengeEntity;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\PortalPhoneLockEntity;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\RefreshTokenEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The schema-only entities (their rows are written in SQL) keep what they
 * are built with.
 */
final class SchemaOnlyEntitiesTest extends TestCase
{
    public function testOtpChallengeEntity(): void
    {
        $id  = Uuid::v7();
        $now = new \DateTimeImmutable('2026-11-01 13:00:00');

        $entity = new OtpChallengeEntity(
            id: $id,
            phone: '+33612345678',
            purpose: 'email_verification',
            subjectType: 'user',
            subjectRef: '01960000-0000-7000-8000-000000000001',
            targetEmail: 'owner@example.com',
            codeHash: 'hash',
            attempts: 1,
            sendCount: 2,
            expiresAt: $now->modify('+10 minutes'),
            resendAvailableAt: $now->modify('+30 seconds'),
            lastChannel: 'email',
            smsSuppressed: false,
            consumedAt: null,
            onboardingTokenHash: null,
            onboardingExpiresAt: null,
            onboardingRevokedAt: null,
            createdAt: $now,
        );

        self::assertSame($id, $entity->id);
        self::assertSame('owner@example.com', $entity->targetEmail);
        self::assertSame(2, $entity->sendCount);
        self::assertSame($now, $entity->createdAt);
    }

    public function testPortalPhoneLockEntity(): void
    {
        self::assertSame('+33612345678', new PortalPhoneLockEntity('+33612345678')->phone);
    }

    public function testRefreshTokenEntity(): void
    {
        $id     = Uuid::v7();
        $family = Uuid::v7();
        $user   = Uuid::v7();
        $now    = new \DateTimeImmutable('2026-11-01 13:00:00');

        $entity = new RefreshTokenEntity(
            id: $id,
            familyId: $family,
            userId: $user,
            tokenHash: 'hash',
            createdAt: $now,
            lastUsedAt: null,
            expiresAt: $now->modify('+30 days'),
            rotatedAt: null,
            rotatedToId: null,
            graceSuccessorId: null,
            revokedAt: null,
        );

        self::assertSame($family, $entity->familyId);
        self::assertSame($user, $entity->userId);
        self::assertSame('hash', $entity->tokenHash);
        self::assertNull($entity->revokedAt);
    }
}
