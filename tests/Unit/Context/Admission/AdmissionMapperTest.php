<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Admission;

use App\Context\Admission\Domain\Admission;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\AdmissionStatus;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Domain\ValueObject\ClosureReason;
use App\Context\Admission\Domain\ValueObject\IntakeChannel;
use App\Context\Admission\Domain\ValueObject\LocationStatus;
use App\Context\Admission\Domain\ValueObject\LocationStatusValue;
use App\Context\Admission\Domain\ValueObject\Presenter;
use App\Context\Admission\Domain\ValueObject\PresenterRole;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use App\Context\Admission\Infrastructure\Persistence\Doctrine\AdmissionMapper;
use App\Shared\Domain\ValueObject\PhoneNumber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class AdmissionMapperTest extends TestCase
{
    private AdmissionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new AdmissionMapper();
    }

    public function testMapperSymmetryForActiveAdmissionWithNoPresenter(): void
    {
        $original      = $this->makeAdmission();
        $entity        = $this->mapper->toEntity($original);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame($original->id()->value(), $reconstituted->id()->value());
        self::assertSame($original->clinicId()->toString(), $reconstituted->clinicId()->toString());
        self::assertSame($original->patientId(), $reconstituted->patientId());
        self::assertTrue($reconstituted->isPatientIdentifiedAtOpening());
        self::assertSame(IntakeChannel::WalkIn, $reconstituted->intakeChannel());
        self::assertSame(TriageLevel::Priority, $reconstituted->triageLevel());
        self::assertNull($reconstituted->presenter());
        self::assertSame(LocationStatusValue::InWaitingRoom, $reconstituted->locationStatus()->value());
        self::assertNull($reconstituted->appointmentId());
        self::assertSame('Large mixed-breed dog, brown fur.', $reconstituted->physicalDescription());
        self::assertSame(AdmissionStatus::Active, $reconstituted->status());
        self::assertNull($reconstituted->closureReason());
        self::assertSame('Some notes.', $reconstituted->triageNotes());
        self::assertSame(3, $reconstituted->version());
    }

    public function testMapperSymmetryForClosedAdmission(): void
    {
        $original = $this->makeAdmission(
            status: AdmissionStatus::Closed,
            closureReason: ClosureReason::ConsultationCompleted,
        );
        $entity        = $this->mapper->toEntity($original);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame(AdmissionStatus::Closed, $reconstituted->status());
        self::assertSame(ClosureReason::ConsultationCompleted, $reconstituted->closureReason());
        self::assertNotNull($reconstituted->closedAt());
    }

    public function testMapperSymmetryWithPresenter(): void
    {
        $phone     = PhoneNumber::fromString('+33612345678');
        $presenter = Presenter::create('Jean Dupont', $phone, PresenterRole::Owner);
        $original  = $this->makeAdmission(presenter: $presenter);

        $entity        = $this->mapper->toEntity($original);
        $reconstituted = $this->mapper->toDomain($entity);

        $recoveredPresenter = $reconstituted->presenter();
        self::assertNotNull($recoveredPresenter);
        self::assertSame('Jean Dupont', $recoveredPresenter->name());
        self::assertSame(PresenterRole::Owner, $recoveredPresenter->role());
        self::assertNotNull($recoveredPresenter->phone());
        self::assertSame('+33612345678', $recoveredPresenter->phone()->toString());
    }

    public function testMapperSymmetryWithAppointmentId(): void
    {
        $appointmentId = Uuid::v7()->toString();
        $original      = $this->makeAdmission(appointmentId: $appointmentId);

        $entity        = $this->mapper->toEntity($original);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame($appointmentId, $reconstituted->appointmentId());
    }

    public function testMapperWithNullPresenterPhone(): void
    {
        $presenter = Presenter::create('Marie Curie', null, PresenterRole::ThirdParty);
        $original  = $this->makeAdmission(presenter: $presenter);

        $entity        = $this->mapper->toEntity($original);
        $reconstituted = $this->mapper->toDomain($entity);

        $recoveredPresenter = $reconstituted->presenter();
        self::assertNotNull($recoveredPresenter);
        self::assertSame('Marie Curie', $recoveredPresenter->name());
        self::assertNull($recoveredPresenter->phone());
    }

    private function makeAdmission(
        AdmissionStatus $status = AdmissionStatus::Active,
        ?ClosureReason $closureReason = null,
        ?Presenter $presenter = null,
        ?string $appointmentId = null,
    ): Admission {
        $now = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');

        return Admission::reconstituteFromPersistence(
            id: AdmissionId::fromString('01234567-89ab-cdef-0123-456789abcdef'),
            clinicId: ClinicId::fromString('12345678-9abc-def0-1234-56789abcdef0'),
            patientId: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            isPatientIdentifiedAtOpening: true,
            intakeChannel: IntakeChannel::WalkIn,
            triageLevel: TriageLevel::Priority,
            presenter: $presenter,
            locationStatus: new LocationStatus(LocationStatusValue::InWaitingRoom, $now),
            appointmentId: $appointmentId,
            physicalDescription: 'Large mixed-breed dog, brown fur.',
            status: $status,
            closureReason: $closureReason,
            triageNotes: 'Some notes.',
            version: 3,
            openedAt: $now,
            closedAt: AdmissionStatus::Closed === $status ? $now : null,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
