<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Domain\Account\ClinicAccount;
use App\Context\Clinic\Domain\Account\ClinicAccountRepositoryInterface;
use App\Context\Clinic\Domain\Account\ClinicPlan;
use App\Context\Clinic\Domain\Account\ClinicSuspension;
use App\Context\Clinic\Domain\Account\ParallelSoftware;
use App\Context\Clinic\Domain\Account\SuspensionReason;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalClinicAccountRepository implements ClinicAccountRepositoryInterface
{
    public const string TABLE = 'clinic__clinic_accounts';

    public function __construct(private Connection $connection)
    {
    }

    public function save(ClinicAccount $account): void
    {
        $suspension = $account->suspension();

        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (clinic_id, plan, pilot_ends_on, parallel_software, internal_note, onboarded_at,'
            . ' suspension_reason, suspension_comment, suspended_at, suspended_by_id, suspended_by_name, created_at, updated_at)'
            . ' VALUES (:clinic, :plan, :pilot, :software, :note, :onboarded, :reason, :comment, :suspended, :by, :byName, :created, :updated)'
            . ' AS new ON DUPLICATE KEY UPDATE plan = new.plan, pilot_ends_on = new.pilot_ends_on, parallel_software = new.parallel_software,'
            . ' internal_note = new.internal_note, onboarded_at = new.onboarded_at, suspension_reason = new.suspension_reason,'
            . ' suspension_comment = new.suspension_comment, suspended_at = new.suspended_at, suspended_by_id = new.suspended_by_id,'
            . ' suspended_by_name = new.suspended_by_name, updated_at = new.updated_at',
            [
                'clinic'    => Uuid::fromString($account->clinicId()->toString())->toBinary(),
                'plan'      => $account->plan()->value,
                'pilot'     => $account->pilotEndsOn()?->format('Y-m-d'),
                'software'  => $account->parallelSoftware()?->value,
                'note'      => $account->internalNote(),
                'onboarded' => self::utc($account->onboardedAt()),
                'reason'    => $suspension?->reason()->value,
                'comment'   => $suspension?->comment(),
                'suspended' => self::utc($suspension?->suspendedAt()),
                'by'        => null !== $suspension?->actorId() ? Uuid::fromString($suspension->actorId())->toBinary() : null,
                'byName'    => $suspension?->actorName(),
                'created'   => self::utc($account->createdAt()),
                'updated'   => self::utc($account->updatedAt()),
            ],
        );
    }

    public function findByClinicId(ClinicId $clinicId): ?ClinicAccount
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE clinic_id = :clinic',
            ['clinic' => Uuid::fromString($clinicId->toString())->toBinary()],
        );

        if (false === $row) {
            return null;
        }

        $reason   = RowAccessor::nullableString($row, 'suspension_reason');
        $software = RowAccessor::nullableString($row, 'parallel_software');
        $pilot    = RowAccessor::nullableString($row, 'pilot_ends_on');

        return ClinicAccount::reconstitute(
            clinicId: $clinicId,
            plan: ClinicPlan::from(RowAccessor::string($row, 'plan')),
            pilotEndsOn: null !== $pilot ? new \DateTimeImmutable($pilot . ' 00:00:00', new \DateTimeZone('UTC')) : null,
            parallelSoftware: null !== $software ? ParallelSoftware::from($software) : null,
            internalNote: RowAccessor::string($row, 'internal_note'),
            onboardedAt: self::date(RowAccessor::nullableString($row, 'onboarded_at')),
            suspension: null !== $reason ? ClinicSuspension::of(
                SuspensionReason::from($reason),
                RowAccessor::nullableString($row, 'suspension_comment') ?? '',
                self::date(RowAccessor::nullableString($row, 'suspended_at')) ?? new \DateTimeImmutable('@0'),
                RowAccessor::nullableUuid($row, 'suspended_by_id'),
                RowAccessor::nullableString($row, 'suspended_by_name') ?? 'Kiveto',
            ) : null,
            createdAt: self::date(RowAccessor::string($row, 'created_at')) ?? new \DateTimeImmutable('@0'),
            updatedAt: self::date(RowAccessor::string($row, 'updated_at')) ?? new \DateTimeImmutable('@0'),
        );
    }

    private static function date(?string $value): ?\DateTimeImmutable
    {
        return null !== $value ? new \DateTimeImmutable($value, new \DateTimeZone('UTC')) : null;
    }

    private static function utc(?\DateTimeImmutable $at): ?string
    {
        return $at?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
