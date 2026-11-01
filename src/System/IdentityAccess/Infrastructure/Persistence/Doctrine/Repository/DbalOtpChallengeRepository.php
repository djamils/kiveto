<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\IdentityAccess\Domain\Otp\OnboardingPurpose;
use App\System\IdentityAccess\Domain\Otp\OtpChallenge;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeRepositoryInterface;
use App\System\IdentityAccess\Domain\Otp\OtpChannel;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\System\IdentityAccess\Domain\Otp\OtpSubjectType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Dates are written in UTC. Identity columns (phone, purpose, subject,
 * target address) never change after the insert; `subject_type` is the one
 * the purpose fixes, written for the readers of the table.
 */
final readonly class DbalOtpChallengeRepository implements OtpChallengeRepositoryInterface
{
    private const string TABLE = 'identity_access__otp_challenges';

    public function __construct(private Connection $connection)
    {
    }

    public function save(OtpChallenge $challenge): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE
            . ' (id, phone, purpose, subject_type, subject_ref, target_email, code_hash, attempts, send_count, expires_at, resend_available_at, last_channel, sms_suppressed, consumed_at, onboarding_token_hash, onboarding_purpose, onboarding_expires_at, onboarding_revoked_at, onboarding_consumed_at, created_at)'
            . ' VALUES (:id, :phone, :purpose, :subjectType, :subject, :targetEmail, :code, :attempts, :sends, :expires, :resend, :channel, :suppressed, :consumed, :onboarding, :onboardingPurpose, :onboardingExpires, :onboardingRevoked, :onboardingConsumed, :created)'
            . ' AS new ON DUPLICATE KEY UPDATE code_hash = new.code_hash, attempts = new.attempts, send_count = new.send_count,'
            . ' expires_at = new.expires_at, resend_available_at = new.resend_available_at, last_channel = new.last_channel,'
            . ' consumed_at = new.consumed_at, onboarding_token_hash = new.onboarding_token_hash, onboarding_purpose = new.onboarding_purpose,'
            . ' onboarding_expires_at = new.onboarding_expires_at, onboarding_revoked_at = new.onboarding_revoked_at,'
            . ' onboarding_consumed_at = new.onboarding_consumed_at',
            [
                'id'                 => Uuid::fromString($challenge->id()->toString())->toBinary(),
                'phone'              => $challenge->phone()->toString(),
                'purpose'            => $challenge->purpose()->value,
                'subjectType'        => $challenge->subjectType()?->value,
                'subject'            => $challenge->subjectRef(),
                'targetEmail'        => $challenge->targetEmail(),
                'code'               => $challenge->codeHash(),
                'attempts'           => $challenge->attempts(),
                'sends'              => $challenge->sendCount(),
                'expires'            => self::utc($challenge->expiresAt()),
                'resend'             => self::utc($challenge->resendAvailableAt()),
                'channel'            => $challenge->lastChannel()->value,
                'suppressed'         => (int) $challenge->isSmsSuppressed(),
                'consumed'           => self::nullableUtc($challenge->consumedAt()),
                'onboarding'         => $challenge->onboardingTokenHash(),
                'onboardingPurpose'  => $challenge->onboardingPurpose()?->value,
                'onboardingExpires'  => self::nullableUtc($challenge->onboardingExpiresAt()),
                'onboardingRevoked'  => self::nullableUtc($challenge->onboardingRevokedAt()),
                'onboardingConsumed' => self::nullableUtc($challenge->onboardingConsumedAt()),
                'created'            => self::utc($challenge->createdAt()),
            ],
        );
    }

    public function findById(OtpChallengeId $id): ?OtpChallenge
    {
        return $this->find('id = :key', Uuid::fromString($id->toString())->toBinary(), '');
    }

    public function findByIdForUpdate(OtpChallengeId $id): ?OtpChallenge
    {
        return $this->find('id = :key', Uuid::fromString($id->toString())->toBinary(), ' FOR UPDATE');
    }

    public function findByOnboardingHashForUpdate(string $tokenHash, OnboardingPurpose $purpose): ?OtpChallenge
    {
        return $this->find('onboarding_token_hash = :key AND onboarding_purpose = :onboardingPurpose', $tokenHash, ' FOR UPDATE', ['onboardingPurpose' => $purpose->value]);
    }

    public function revokeOnboardingGrantsForPhone(PhoneNumber $phone, \DateTimeImmutable $at): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET onboarding_revoked_at = :at'
            . ' WHERE phone = :phone AND onboarding_purpose = :invitation AND onboarding_revoked_at IS NULL',
            ['at' => self::utc($at), 'phone' => $phone->toString(), 'invitation' => OnboardingPurpose::INVITATION->value],
        );
    }

    public function deleteForPhoneOrUser(PhoneNumber $phone, string $userId): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE phone = :phone OR (subject_type = :user AND subject_ref = :userId)',
            ['phone' => $phone->toString(), 'user' => OtpSubjectType::USER->value, 'userId' => $userId],
        );
    }

    /**
     * @param array<string, string> $parameters bound besides `key`
     */
    private function find(string $condition, string $key, string $lock, array $parameters = []): ?OtpChallenge
    {
        // Malformed ids never reach SQL: the binary form needs a valid UUID.
        $row = $this->connection->fetchAssociative('SELECT * FROM ' . self::TABLE . ' WHERE ' . $condition . $lock, ['key' => $key, ...$parameters]);

        if (false === $row) {
            return null;
        }

        return OtpChallenge::reconstitute(
            id: OtpChallengeId::fromString(RowAccessor::uuid($row, 'id')),
            phone: PhoneNumber::fromString(RowAccessor::string($row, 'phone')),
            purpose: OtpPurpose::from(RowAccessor::string($row, 'purpose')),
            subjectRef: RowAccessor::nullableString($row, 'subject_ref'),
            codeHash: RowAccessor::string($row, 'code_hash'),
            attempts: RowAccessor::int($row, 'attempts'),
            sendCount: RowAccessor::int($row, 'send_count'),
            expiresAt: self::date(RowAccessor::string($row, 'expires_at')),
            resendAvailableAt: self::date(RowAccessor::string($row, 'resend_available_at')),
            lastChannel: OtpChannel::from(RowAccessor::string($row, 'last_channel')),
            smsSuppressed: 1 === RowAccessor::int($row, 'sms_suppressed'),
            consumedAt: self::nullableDate(RowAccessor::nullableString($row, 'consumed_at')),
            onboardingTokenHash: RowAccessor::nullableString($row, 'onboarding_token_hash'),
            onboardingExpiresAt: self::nullableDate(RowAccessor::nullableString($row, 'onboarding_expires_at')),
            onboardingRevokedAt: self::nullableDate(RowAccessor::nullableString($row, 'onboarding_revoked_at')),
            createdAt: self::date(RowAccessor::string($row, 'created_at')),
            targetEmail: RowAccessor::nullableString($row, 'target_email'),
            onboardingPurpose: OnboardingPurpose::tryFrom(RowAccessor::nullableString($row, 'onboarding_purpose') ?? ''),
            onboardingConsumedAt: self::nullableDate(RowAccessor::nullableString($row, 'onboarding_consumed_at')),
        );
    }

    private static function date(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }

    private static function nullableDate(?string $value): ?\DateTimeImmutable
    {
        return null === $value ? null : self::date($value);
    }

    private static function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function nullableUtc(?\DateTimeImmutable $at): ?string
    {
        return null === $at ? null : self::utc($at);
    }
}
