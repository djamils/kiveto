<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Consultation\Factory;

use App\Context\Consultation\Domain\ValueObject\ConsultationStatus;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ConsultationEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ConsultationEntity>
 */
final class ConsultationEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ConsultationEntity::class;
    }

    public function withId(string $id): self
    {
        return $this->afterInstantiate(static function (ConsultationEntity $entity) use ($id): void {
            $entity->setId(Uuid::fromString($id)->toBinary());
        });
    }

    public function withClinicId(string $clinicId): self
    {
        return $this->afterInstantiate(static function (ConsultationEntity $entity) use ($clinicId): void {
            $entity->setClinicId(Uuid::fromString($clinicId)->toBinary());
        });
    }

    public function withAppointmentId(?string $appointmentId): self
    {
        return $this->afterInstantiate(static function (ConsultationEntity $entity) use ($appointmentId): void {
            $entity->setAppointmentId(null !== $appointmentId ? Uuid::fromString($appointmentId)->toBinary() : null);
        });
    }

    public function withAdmissionId(string $admissionId): self
    {
        return $this->afterInstantiate(static function (ConsultationEntity $entity) use ($admissionId): void {
            $entity->setAdmissionId(Uuid::fromString($admissionId)->toBinary());
        });
    }

    public function withPatientId(string $patientId): self
    {
        return $this->afterInstantiate(static function (ConsultationEntity $entity) use ($patientId): void {
            $entity->setPatientId(Uuid::fromString($patientId)->toBinary());
        });
    }

    public function withPractitionerUserId(string $userId): self
    {
        return $this->afterInstantiate(static function (ConsultationEntity $entity) use ($userId): void {
            $entity->setPractitionerUserId(Uuid::fromString($userId)->toBinary());
        });
    }

    public function withStatus(ConsultationStatus $status): self
    {
        return $this->afterInstantiate(static function (ConsultationEntity $entity) use ($status): void {
            $entity->setStatus($status->value);
        });
    }

    protected function defaults(): array|callable
    {
        return [
            'id'                 => Uuid::v7()->toBinary(),
            'clinicId'           => Uuid::v7()->toBinary(),
            'appointmentId'      => null,
            'admissionId'        => Uuid::v7()->toBinary(),
            'patientId'          => Uuid::v7()->toBinary(),
            'practitionerUserId' => Uuid::v7()->toBinary(),
            'status'             => ConsultationStatus::OPEN->value,
            'chiefComplaint'     => null,
            'summary'            => null,
            'weightKg'           => null,
            'temperatureC'       => null,
            'startedAtUtc'       => new \DateTimeImmutable(),
            'closedAtUtc'        => null,
            'createdAtUtc'       => new \DateTimeImmutable(),
            'updatedAtUtc'       => new \DateTimeImmutable(),
        ];
    }
}
