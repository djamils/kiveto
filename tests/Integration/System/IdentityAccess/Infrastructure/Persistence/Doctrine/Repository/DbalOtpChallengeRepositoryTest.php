<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Domain\Otp\OtpChallenge;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeRepositoryInterface;
use App\System\IdentityAccess\Domain\Otp\OtpChannel;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\System\IdentityAccess\Domain\Otp\OtpSubjectType;
use App\System\IdentityAccess\Domain\Otp\OtpVerificationResult;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalOtpChallengeRepositoryTest extends KernelTestCase
{
    private const string LOGIN      = '01960000-0000-7000-8000-0000000005a1';
    private const string INVITATION = '01960000-0000-7000-8000-0000000005a2';
    private const string OTHER      = '01960000-0000-7000-8000-0000000005a3';
    private const string INVITE_REF = '01960000-0000-7000-8000-0000000005b1';
    private const string PHONE      = '+33612340501';
    private const string USER       = '01960000-0000-7000-8000-0000000005e1';

    public function testEveryColumnOfANewChallengeIsReadBack(): void
    {
        $challenges = $this->challenges();
        // A Paris time: the dates are stored in UTC and read back as UTC.
        $now = new \DateTimeImmutable('2026-10-26 08:14:00', new \DateTimeZone('Europe/Paris'));
        $challenges->save(OtpChallenge::start(OtpChallengeId::fromString(self::INVITATION), PhoneNumber::fromString(self::PHONE), OtpPurpose::INVITATION, self::INVITE_REF, hash('sha256', 'c1'), $now, true));

        self::assertNull($challenges->findById(OtpChallengeId::fromString(self::OTHER)));
        $found = $challenges->findById(OtpChallengeId::fromString(self::INVITATION));
        self::assertNotNull($found);
        self::assertSame([self::INVITATION, self::PHONE, OtpPurpose::INVITATION, self::INVITE_REF, hash('sha256', 'c1'), 0, 1, OtpChannel::SMS, true], [
            $found->id()->toString(), $found->phone()->toString(), $found->purpose(), $found->subjectRef(), $found->codeHash(), $found->attempts(), $found->sendCount(), $found->lastChannel(), $found->isSmsSuppressed(),
        ]);
        self::assertSame(['2026-10-26 07:24:00', '2026-10-26 07:14:30', '2026-10-26 07:14:00', 'UTC'], [
            $found->expiresAt()->format('Y-m-d H:i:s'), $found->resendAvailableAt()->format('Y-m-d H:i:s'), $found->createdAt()->format('Y-m-d H:i:s'), $found->createdAt()->getTimezone()->getName(),
        ]);
        self::assertSame([null, null, null, null], [$found->consumedAt(), $found->onboardingTokenHash(), $found->onboardingExpiresAt(), $found->onboardingRevokedAt()]);
    }

    /** S5e: the subject and the target address survive the round trip; the subject type is written for the readers of the table. */
    public function testAnEmailVerificationChallengeKeepsItsSubjectAndAddress(): void
    {
        $challenges = $this->challenges();
        $now        = new \DateTimeImmutable('2026-11-01 13:05:00', new \DateTimeZone('UTC'));
        $challenges->save(OtpChallenge::start(OtpChallengeId::fromString(self::OTHER), PhoneNumber::fromString(self::PHONE), OtpPurpose::EMAIL_VERIFICATION, self::USER, hash('sha256', 'c1'), $now, targetEmail: 'new@example.com'));
        $challenges->save(OtpChallenge::start(OtpChallengeId::fromString(self::INVITATION), PhoneNumber::fromString(self::PHONE), OtpPurpose::INVITATION, self::INVITE_REF, hash('sha256', 'c1'), $now));

        $found = $challenges->findById(OtpChallengeId::fromString(self::OTHER));
        self::assertNotNull($found);
        self::assertSame([OtpPurpose::EMAIL_VERIFICATION, OtpSubjectType::USER, self::USER, 'new@example.com', OtpChannel::EMAIL], [
            $found->purpose(), $found->subjectType(), $found->subjectRef(), $found->targetEmail(), $found->lastChannel(),
        ]);

        $connection = $this->em()->getConnection();
        self::assertSame(
            [['email_verification', 'user', self::USER, 'new@example.com'], ['invitation', 'invitation', self::INVITE_REF, null]],
            array_map(static fn (array $row): array => array_values($row), $connection->fetchAllAssociative(
                'SELECT purpose, subject_type, subject_ref, target_email FROM identity_access__otp_challenges WHERE phone = ? ORDER BY purpose',
                [self::PHONE],
            )),
        );
    }

    public function testTheStateOfAKnownChallengeIsUpdatedUnderLock(): void
    {
        $challenges = $this->challenges();
        $now        = new \DateTimeImmutable('2026-10-26 07:14:00', new \DateTimeZone('UTC'));
        $challenges->save($this->login(self::LOGIN, $now));

        $this->em()->wrapInTransaction(function () use ($challenges, $now): void {
            $locked = $challenges->findByIdForUpdate(OtpChallengeId::fromString(self::LOGIN));
            self::assertNotNull($locked);
            $locked->verify(hash('sha256', 'wrong'), $now, OtpPurpose::LOGIN);
            $locked->resend(hash('sha256', 'c2'), OtpChannel::SMS, $now->modify('+1 minute'));
            $locked->verify(hash('sha256', 'wrong'), $now->modify('+2 minutes'), OtpPurpose::LOGIN);
            self::assertSame(OtpVerificationResult::VERIFIED, $locked->verify(hash('sha256', 'c2'), $now->modify('+3 minutes'), OtpPurpose::LOGIN));
            $locked->grantOnboarding(hash('sha256', 'grant'), $now->modify('+3 minutes'));
            $locked->revokeOnboarding($now->modify('+4 minutes'));
            $challenges->save($locked);
        });

        self::assertNull($challenges->findByIdForUpdate(OtpChallengeId::fromString(self::OTHER)));
        $found = $challenges->findById(OtpChallengeId::fromString(self::LOGIN));
        self::assertNotNull($found);
        self::assertSame([hash('sha256', 'c2'), 1, 2, '07:25:00', '07:15:30', '07:17:00', hash('sha256', 'grant'), '07:32:00', '07:18:00'], [
            $found->codeHash(), $found->attempts(), $found->sendCount(), $found->expiresAt()->format('H:i:s'), $found->resendAvailableAt()->format('H:i:s'),
            $found->consumedAt()?->format('H:i:s'), $found->onboardingTokenHash(), $found->onboardingExpiresAt()?->format('H:i:s'), $found->onboardingRevokedAt()?->format('H:i:s'),
        ]);
        self::assertSame([self::PHONE, OtpPurpose::LOGIN, null, false, '07:14:00'], [$found->phone()->toString(), $found->purpose(), $found->subjectRef(), $found->isSmsSuppressed(), $found->createdAt()->format('H:i:s')]);
    }

    public function testAGrantIsFoundByItsDigestAndTheGrantsOfAPhoneAreRevokedTogether(): void
    {
        $challenges = $this->challenges();
        $now        = new \DateTimeImmutable('2026-10-26 07:14:00', new \DateTimeZone('UTC'));
        $this->granted(self::LOGIN, self::PHONE, 'grant-1', $now);
        $this->granted(self::INVITATION, self::PHONE, 'grant-2', $now);
        $this->granted(self::OTHER, '+33612340502', 'grant-3', $now);
        $challenges->save($this->login('01960000-0000-7000-8000-0000000005a4', $now));

        self::assertSame(self::INVITATION, $this->em()->wrapInTransaction(static fn (): ?string => $challenges->findByOnboardingHashForUpdate(hash('sha256', 'grant-2'))?->id()->toString()));
        self::assertNull($challenges->findByOnboardingHashForUpdate(hash('sha256', 'unknown')));

        $at = $now->modify('+5 minutes');
        self::assertSame(2, $challenges->revokeOnboardingGrantsForPhone(PhoneNumber::fromString(self::PHONE), $at));
        self::assertSame(0, $challenges->revokeOnboardingGrantsForPhone(PhoneNumber::fromString(self::PHONE), $at->modify('+1 minute')), 'already revoked');

        self::assertSame('07:19:00', $challenges->findById(OtpChallengeId::fromString(self::LOGIN))?->onboardingRevokedAt()?->format('H:i:s'));
        self::assertFalse($challenges->findById(OtpChallengeId::fromString(self::INVITATION))?->isOnboardingUsable($at));
        self::assertTrue($challenges->findById(OtpChallengeId::fromString(self::OTHER))?->isOnboardingUsable($at), 'another phone keeps its grant');
    }

    private function granted(string $id, string $phone, string $secret, \DateTimeImmutable $now): void
    {
        $challenge = OtpChallenge::start(OtpChallengeId::fromString($id), PhoneNumber::fromString($phone), OtpPurpose::LOGIN, null, hash('sha256', 'c1'), $now);
        $challenge->verify(hash('sha256', 'c1'), $now, OtpPurpose::LOGIN);
        $challenge->grantOnboarding(hash('sha256', $secret), $now);
        $this->challenges()->save($challenge);
    }

    private function login(string $id, \DateTimeImmutable $now): OtpChallenge
    {
        return OtpChallenge::start(OtpChallengeId::fromString($id), PhoneNumber::fromString(self::PHONE), OtpPurpose::LOGIN, null, hash('sha256', 'c1'), $now);
    }

    private function challenges(): OtpChallengeRepositoryInterface
    {
        $repository = self::getContainer()->get(OtpChallengeRepositoryInterface::class);
        \assert($repository instanceof OtpChallengeRepositoryInterface);

        return $repository;
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
