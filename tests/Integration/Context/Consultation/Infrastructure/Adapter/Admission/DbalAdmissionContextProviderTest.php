<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Consultation\Infrastructure\Adapter\Admission;

use App\Context\Admission\Domain\ValueObject\AdmissionStatus;
use App\Context\Admission\Domain\ValueObject\ClosureReason;
use App\Context\Consultation\Application\Port\AdmissionContextProviderInterface;
use App\Fixtures\Context\Admission\Factory\AdmissionEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DbalAdmissionContextProviderTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID    = '01960000-0000-7000-8000-0000000000b1';
    private const string PATIENT_ID   = '01960000-0000-7000-8000-0000000000b2';
    private const string ADMISSION_ID = '01960000-0000-7000-8000-0000000000b3';

    private AdmissionContextProviderInterface $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $provider = self::getContainer()->get(AdmissionContextProviderInterface::class);
        \assert($provider instanceof AdmissionContextProviderInterface);
        $this->provider = $provider;
    }

    public function testAnActiveAdmissionIsReportedAsOpen(): void
    {
        $this->admission(AdmissionStatus::Active);

        $context = $this->provider->getAdmissionContext(self::ADMISSION_ID);

        self::assertSame(self::PATIENT_ID, $context->patientId);
        self::assertSame(self::CLINIC_ID, $context->clinicId);
        self::assertTrue($context->isOpen);
    }

    public function testAClosedAdmissionIsReportedAsOver(): void
    {
        $this->admission(AdmissionStatus::Closed);

        self::assertFalse($this->provider->getAdmissionContext(self::ADMISSION_ID)->isOpen);
    }

    public function testAnUnknownAdmissionIsRejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Admission not found');

        $this->provider->getAdmissionContext(Uuid::v7()->toRfc4122());
    }

    private function admission(AdmissionStatus $status): void
    {
        AdmissionEntityFactory::new()
            ->withId(self::ADMISSION_ID)
            ->withClinicId(self::CLINIC_ID)
            ->withPatientId(self::PATIENT_ID)
            ->create([
                'status'        => $status,
                'closureReason' => AdmissionStatus::Closed === $status ? ClosureReason::ConsultationCompleted : null,
                'closedAt'      => AdmissionStatus::Closed === $status ? new \DateTimeImmutable() : null,
            ])
        ;
    }
}
