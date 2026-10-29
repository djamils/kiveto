<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\IdentityAccess\Domain\Otp\OtpChallenge;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeRepositoryInterface;
use App\System\IdentityAccess\Domain\Otp\OtpChannel;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Dates are written in UTC. Identity columns (phone, purpose, invitation)
 * never change after the insert.
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
            . ' (id, phone, purpose, invitation_ref, code_hash, attempts, send_count, expires_at, resend_available_at, last_channel, sms_suppressed, consumed_at, onboarding_token_hash, onboarding_expires_at, onboarding_revoked_at, created_at)'
            . ' VALUES (:id, :phone, :purpose, :invitation, :code, :attempts, :sends, :expires, :resend, :channel, :suppressed, :consumed, :onboarding, :onboardingExpires, :onboardingRevoked, :created)'
            . ' AS new ON DUPLICATE KEY UPDATE code_hash = new.code_hash, attempts = new.attempts, send_count = new.send_count,'
            . ' expires_at = new.expires_at, resend_available_at = new.resend_available_at, last_channel = new.last_channel,'
            . ' consumed_at = new.consumed_at, onboarding_token_hash = new.onboarding_token_hash,'
            . ' onboarding_expires_at = new.onboarding_expires_at, onboarding_revoked_at = new.onboarding_revoked_at',
            [
                'id'                => Uuid::fromString($challenge->id()->toString())->toBinary(),
                'phone'             => $challenge->phone()->toString(),
                'purpose'           => $challenge->purpose()->value,
                'invitation'        => $challenge->invitationRef(),
                'code'              => $challenge->codeHash(),
                'attempts'          => $challenge->attempts(),
                'sends'             => $challenge->sendCount(),
                'expires'           => self::utc($challenge->expiresAt()),
                'resend'            => self::utc($challenge->resendAvailableAt()),
                'channel'           => $challenge->lastChannel()->value,
                'suppressed'        => (int) $challenge->isSmsSuppressed(),
                'consumed'          => self::nullableUtc($challenge->consumedAt()),
                'onboarding'        => $challenge->onboardingTokenHash(),
                'onboardingExpires' => self::nullableUtc($challenge->onboardingExpiresAt()),
                'onboardingRevoked' => self::nullableUtc($challenge->onboardingRevokedAt()),
                'created'           => self::utc($challenge->createdAt()),
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

    public function findByOnboardingHashForUpdate(string $tokenHash): ?OtpChallenge
    {
        return $this->find('onboarding_token_hash = :key', $tokenHash, ' FOR UPDATE');
    }

    public function revokeOnboardingGrantsForPhone(PhoneNumber $phone, \DateTimeImmutable $at): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE ' . self::TABLE . ' SET onboarding_revoked_at = :at'
            . ' WHERE phone = :phone AND onboarding_token_hash IS NOT NULL AND onboarding_revoked_at IS NULL',
            ['at' => self::utc($at), 'phone' => $phone->toString()],
        );
    }

    private function find(string $condition, string $key, string $lock): ?OtpChallenge
    {
        // Malformed ids never reach SQL: the binary form needs a valid UUID.
        $row = $this->connection->fetchAssociative('SELECT * FROM ' . self::TABLE . ' WHERE ' . $condition . $lock, ['key' => $key]);

        if (false === $row) {
            return null;
        }

        return OtpChallenge::reconstitute(
            id: OtpChallengeId::fromString(RowAccessor::uuid($row, 'id')),
            phone: PhoneNumber::fromString(RowAccessor::string($row, 'phone')),
            purpose: OtpPurpose::from(RowAccessor::string($row, 'purpose')),
            invitationRef: RowAccessor::nullableString($row, 'invitation_ref'),
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
