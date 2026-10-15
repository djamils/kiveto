<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Infrastructure\Messaging\Consumer;

use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Infrastructure\Messaging\Consumer\PatientReconciledIntoHandler;
use App\Context\Patient\Domain\Event\PatientReconciledIntoIntegrationEvent;
use PHPUnit\Framework\TestCase;

final class PatientReconciledIntoHandlerTest extends TestCase
{
    private const string CLINIC_ID = '22222222-2222-4222-8222-222222222222';
    private const string SOURCE_ID = '66666666-6666-4666-8666-666666666666';
    private const string TARGET_ID = '77777777-7777-4777-8777-777777777777';

    public function testTheMergedRecordConsultationsAreTransferredWithinTheClinic(): void
    {
        $consultations = $this->createMock(ConsultationRepositoryInterface::class);
        $consultations->expects(self::once())
            ->method('transferToPatient')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (PatientId $id): bool => self::SOURCE_ID === $id->toString()),
                self::callback(static fn (PatientId $id): bool => self::TARGET_ID === $id->toString()),
            )
        ;

        (new PatientReconciledIntoHandler($consultations))(new PatientReconciledIntoIntegrationEvent(
            sourcePatientId: self::SOURCE_ID,
            targetPatientId: self::TARGET_ID,
            clinicId: self::CLINIC_ID,
        ));
    }
}
