<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Consultation\Application\Command\CloseConsultation;

use App\Context\Admission\Application\Command\OpenAdmissionForWalkIn\OpenAdmissionForWalkIn;
use App\Context\Admission\Application\Port\AdmissionReadRepositoryInterface;
use App\Context\Consultation\Application\Command\CloseConsultation\CloseConsultation;
use App\Context\Consultation\Application\Command\StartConsultationFromAdmission\StartConsultationFromAdmission;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * Closing a consultation sends the patient back to the desk, where the Flux du
 * jour lists them as waiting to be charged. The visit itself is closed later,
 * by the checkout.
 */
final class CloseConsultationSendsToReceptionTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '01960000-0000-7000-8000-0000000000f1';
    private const string VET_ID    = '01960000-0000-7000-8000-0000000000f2';

    private CommandBusInterface $commandBus;

    private AdmissionReadRepositoryInterface $admissions;

    protected function setUp(): void
    {
        parent::setUp();

        $commandBus = self::getContainer()->get(CommandBusInterface::class);
        \assert($commandBus instanceof CommandBusInterface);
        $this->commandBus = $commandBus;

        $admissions = self::getContainer()->get(AdmissionReadRepositoryInterface::class);
        \assert($admissions instanceof AdmissionReadRepositoryInterface);
        $this->admissions = $admissions;

        // Starting a consultation checks the practitioner belongs to the clinic.
        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::VET_ID)
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2020-01-01')])
        ;
    }

    public function testThePatientGoesBackToTheDeskOnceTheConsultationIsClosed(): void
    {
        $admissionId    = $this->openAdmission('Luna');
        $consultationId = $this->startConsultation($admissionId);

        self::assertSame('in_consultation_room', $this->locationOf($admissionId));

        $this->commandBus->dispatch(new CloseConsultation(
            consultationId: $consultationId,
            closedByUserId: self::VET_ID,
            summary: null,
        ));

        // Still on the board — the visit ends at the checkout, not here.
        self::assertSame('at_reception', $this->locationOf($admissionId));
    }

    public function testAVisitWithAnotherRunningConsultationStaysInTheRoom(): void
    {
        $admissionId = $this->openAdmission('Milou');

        $first  = $this->startConsultation($admissionId);
        $second = $this->startConsultation($admissionId);

        $this->commandBus->dispatch(new CloseConsultation($first, self::VET_ID, null));
        self::assertSame('in_consultation_room', $this->locationOf($admissionId));

        $this->commandBus->dispatch(new CloseConsultation($second, self::VET_ID, null));
        self::assertSame('at_reception', $this->locationOf($admissionId));
    }

    private function locationOf(string $admissionId): ?string
    {
        foreach ($this->admissions->findAllActiveForClinic(self::CLINIC_ID) as $item) {
            if ($item->admissionId === $admissionId) {
                return $item->locationStatus;
            }
        }

        return null;
    }

    private function openAdmission(string $animalName): string
    {
        $animal = AnimalEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->create(['name' => $animalName])
        ;

        $admissionId = $this->commandBus->dispatch(new OpenAdmissionForWalkIn(
            clinicId: self::CLINIC_ID,
            knownAnimalId: $animal->getId()->toString(),
            animalName: $animalName,
        ));

        \assert(\is_string($admissionId));

        return $admissionId;
    }

    private function startConsultation(string $admissionId): string
    {
        $consultationId = $this->commandBus->dispatch(new StartConsultationFromAdmission(
            admissionId: $admissionId,
            startedByUserId: self::VET_ID,
        ));

        \assert(\is_string($consultationId));

        return $consultationId;
    }
}
