<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Admission\Factory;

use App\Context\Admission\Domain\ValueObject\AdmissionStatus;
use App\Context\Admission\Domain\ValueObject\ClosureReason;
use App\Context\Admission\Domain\ValueObject\IntakeChannel;
use App\Context\Admission\Domain\ValueObject\LocationStatusValue;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use App\Context\Admission\Infrastructure\Persistence\Doctrine\Entity\AdmissionEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<AdmissionEntity>
 */
final class AdmissionEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return AdmissionEntity::class;
    }

    public function withId(string $id): self
    {
        return $this->afterInstantiate(function (AdmissionEntity $entity) use ($id): void {
            $entity->setId(Uuid::fromString($id));
        });
    }

    public function withClinicId(string $clinicId): self
    {
        return $this->afterInstantiate(function (AdmissionEntity $entity) use ($clinicId): void {
            $entity->setClinicId(Uuid::fromString($clinicId));
        });
    }

    public function withPatientId(string $patientId): self
    {
        return $this->afterInstantiate(function (AdmissionEntity $entity) use ($patientId): void {
            $entity->setPatientId(Uuid::fromString($patientId));
        });
    }

    public function active(): self
    {
        return $this->afterInstantiate(function (AdmissionEntity $entity): void {
            $entity->setStatus(AdmissionStatus::Active);
            $entity->setClosureReason(null);
            $entity->setClosedAt(null);
        });
    }

    public function closed(ClosureReason $reason): self
    {
        $closedAt = \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-3 months'));

        return $this->afterInstantiate(function (AdmissionEntity $entity) use ($reason, $closedAt): void {
            $entity->setStatus(AdmissionStatus::Closed);
            $entity->setClosureReason($reason);
            $entity->setClosedAt($closedAt);
        });
    }

    /**
     * Patient currently waiting in the waiting room.
     */
    public function inWaitingRoom(): self
    {
        return $this->afterInstantiate(function (AdmissionEntity $entity): void {
            $entity->setLocationStatusValue(LocationStatusValue::InWaitingRoom);
            $entity->setLocationStatusEnteredAt(new \DateTimeImmutable());
        });
    }

    /**
     * Emergency triage level with third-party presenter.
     */
    public function emergency(): self
    {
        return $this->afterInstantiate(function (AdmissionEntity $entity): void {
            $entity->setTriageLevel(TriageLevel::Emergency);
        });
    }

    /**
     * Patient not identified at opening — no presenter name or role.
     */
    public function unidentified(): self
    {
        return $this->afterInstantiate(function (AdmissionEntity $entity): void {
            $entity->setIsPatientIdentifiedAtOpening(false);
            $entity->setPresenterName(null);
            $entity->setPresenterPhone(null);
            $entity->setPresenterRole(null);
        });
    }

    protected function defaults(): array|callable
    {
        return [
            'id'                           => Uuid::v7(),
            'clinicId'                     => Uuid::v7(),
            'patientId'                    => Uuid::v7(),
            'isPatientIdentifiedAtOpening' => true,
            'intakeChannel'                => IntakeChannel::WalkIn,
            'triageLevel'                  => TriageLevel::Standard,
            'presenterName'                => null,
            'presenterPhone'               => null,
            'presenterRole'                => null,
            'locationStatusValue'          => LocationStatusValue::InWaitingRoom,
            'locationStatusEnteredAt'      => new \DateTimeImmutable(),
            'status'                       => AdmissionStatus::Active,
            'closureReason'                => null,
            'appointmentId'                => null,
            'physicalDescription'          => null,
            'triageNotes'                  => null,
            'version'                      => 1,
            'openedAt'                     => \DateTimeImmutable::createFromMutable(
                self::faker()->dateTimeBetween('-1 month')
            ),
            'closedAt'  => null,
            'createdAt' => \DateTimeImmutable::createFromMutable(
                self::faker()->dateTimeBetween('-1 month')
            ),
            'updatedAt' => \DateTimeImmutable::createFromMutable(
                self::faker()->dateTimeBetween('-1 week')
            ),
        ];
    }
}
