<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Schema of the one-time code challenges; rows are written and read in SQL
 * by DbalOtpChallengeRepository.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_otp_onboarding_hash', columns: ['onboarding_token_hash'])]
#[ORM\Index(name: 'idx_otp_phone', columns: ['phone'])]
class OtpChallengeEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: UuidType::NAME)]
        public Uuid $id,
        #[ORM\Column(type: 'string', length: 16)]
        public string $phone,
        #[ORM\Column(type: 'string', length: 20)]
        public string $purpose,
        #[ORM\Column(name: 'subject_type', type: 'string', length: 20, nullable: true)]
        public ?string $subjectType,
        #[ORM\Column(name: 'subject_ref', type: 'string', length: 36, nullable: true)]
        public ?string $subjectRef,
        #[ORM\Column(name: 'target_email', type: 'string', length: 180, nullable: true)]
        public ?string $targetEmail,
        #[ORM\Column(name: 'code_hash', type: 'string', length: 64, options: ['fixed' => true])]
        public string $codeHash,
        #[ORM\Column(type: 'smallint')]
        public int $attempts,
        #[ORM\Column(name: 'send_count', type: 'smallint')]
        public int $sendCount,
        #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $expiresAt,
        #[ORM\Column(name: 'resend_available_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $resendAvailableAt,
        #[ORM\Column(name: 'last_channel', type: 'string', length: 10)]
        public string $lastChannel,
        #[ORM\Column(name: 'sms_suppressed', type: 'boolean')]
        public bool $smsSuppressed,
        #[ORM\Column(name: 'consumed_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $consumedAt,
        #[ORM\Column(name: 'onboarding_token_hash', type: 'string', length: 64, nullable: true, options: ['fixed' => true])]
        public ?string $onboardingTokenHash,
        #[ORM\Column(name: 'onboarding_expires_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $onboardingExpiresAt,
        #[ORM\Column(name: 'onboarding_revoked_at', type: 'datetime_immutable', nullable: true)]
        public ?\DateTimeImmutable $onboardingRevokedAt,
        #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
