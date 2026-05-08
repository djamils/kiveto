<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Admission;

use App\Context\Admission\Application\Command\UpdateAnonymousAdmission\UpdateAnonymousAdmission;
use App\Context\Admission\Application\Command\UpdateAnonymousAdmission\UpdateAnonymousAdmissionHandler;
use App\Context\Admission\Domain\Admission;
use App\Context\Admission\Domain\Repository\AdmissionRepositoryInterface;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Domain\ValueObject\IntakeChannel;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class UpdateAnonymousAdmissionHandlerTest extends TestCase
{
    private AdmissionRepositoryInterface&MockObject $admissionRepository;
    private ClockInterface&Stub $clock;
    private UpdateAnonymousAdmissionHandler $handler;

    private string $clinicId    = '12345678-9abc-def0-1234-56789abcdef0';
    private string $admissionId = '01234567-89ab-cdef-0123-456789abcdef';

    protected function setUp(): void
    {
        $this->admissionRepository = $this->createMock(AdmissionRepositoryInterface::class);
        $this->clock               = $this->createStub(ClockInterface::class);
        $this->handler             = new UpdateAnonymousAdmissionHandler(
            $this->admissionRepository,
            $this->clock,
        );
    }

    public function testBothFieldsNullDoesNotSave(): void
    {
        $this->admissionRepository->expects(self::never())->method('get');
        $this->admissionRepository->expects(self::never())->method('save');

        ($this->handler)(new UpdateAnonymousAdmission(
            clinicId: $this->clinicId,
            admissionId: $this->admissionId,
            physicalDescription: null,
            triageNotes: null,
        ));
    }

    public function testOnlyPhysicalDescriptionCallsUpdateDescriptionAndSaves(): void
    {
        $now = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');
        $this->clock->method('now')->willReturn($now);

        $admission = $this->makeAdmission();

        $this->admissionRepository->expects(self::once())
            ->method('get')
            ->willReturn($admission)
        ;

        $this->admissionRepository->expects(self::once())
            ->method('save')
            ->with($admission)
        ;

        ($this->handler)(new UpdateAnonymousAdmission(
            clinicId: $this->clinicId,
            admissionId: $this->admissionId,
            physicalDescription: 'fat grey cat',
            triageNotes: null,
        ));

        self::assertSame('fat grey cat', $admission->physicalDescription());
        self::assertNull($admission->triageNotes());
    }

    public function testBothFieldsSetCallsBothMethodsAndSaves(): void
    {
        $now = new \DateTimeImmutable('2024-06-01T10:00:00+00:00');
        $this->clock->method('now')->willReturn($now);

        $admission = $this->makeAdmission();

        $this->admissionRepository->expects(self::once())
            ->method('get')
            ->willReturn($admission)
        ;

        $this->admissionRepository->expects(self::once())
            ->method('save')
            ->with($admission)
        ;

        ($this->handler)(new UpdateAnonymousAdmission(
            clinicId: $this->clinicId,
            admissionId: $this->admissionId,
            physicalDescription: 'chat tigré, ~3kg',
            triageNotes: 'Trouvé rue St-Denis',
        ));

        self::assertSame('chat tigré, ~3kg', $admission->physicalDescription());
        self::assertSame('Trouvé rue St-Denis', $admission->triageNotes());
    }

    private function makeAdmission(): Admission
    {
        return Admission::open(
            id: AdmissionId::fromString($this->admissionId),
            clinicId: ClinicId::fromString($this->clinicId),
            patientId: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            isPatientIdentified: false,
            channel: IntakeChannel::Emergency,
            triage: TriageLevel::Standard,
            presenter: null,
            now: new \DateTimeImmutable('2024-06-01T10:00:00+00:00'),
        );
    }
}
