<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Consultation\Application\Command\CloseConsultation;

use App\Context\Admission\Application\Command\OpenAdmissionForWalkIn\OpenAdmissionForWalkIn;
use App\Context\Admission\Application\Port\AdmissionReadRepositoryInterface;
use App\Context\Admission\Application\Port\WaitingRoomItemDto;
use App\Context\Consultation\Application\Command\CloseConsultation\CloseConsultation;
use App\Context\Consultation\Application\Command\StartConsultationFromAdmission\StartConsultationFromAdmission;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * Closing a consultation has to end the visit behind it, otherwise the patient
 * stays forever in the "Pris en charge" column of the Flux du jour instead of
 * moving to "Sortie".
 */
final class CloseConsultationEndsTheVisitTest extends KernelTestCase
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

    public function testTheVisitIsOverOnceTheConsultationIsClosed(): void
    {
        $admissionId    = $this->openAdmission('Luna');
        $consultationId = $this->startConsultation($admissionId);

        self::assertContains(
            $admissionId,
            $this->activeAdmissionIds(),
            'the patient is on the board while the consultation runs',
        );

        $this->commandBus->dispatch(new CloseConsultation(
            consultationId: $consultationId,
            closedByUserId: self::VET_ID,
            summary: null,
        ));

        self::assertNotContains($admissionId, $this->activeAdmissionIds());

        $discharged = $this->admissions->findClosedForClinicSince(
            self::CLINIC_ID,
            new \DateTimeImmutable('-1 hour'),
        );

        $closed = null;

        foreach ($discharged as $item) {
            if ($item->admissionId === $admissionId) {
                $closed = $item;
            }
        }

        self::assertNotNull($closed, 'the patient shows up as discharged');
        self::assertSame('consultation_completed', $closed->closureReason);
        self::assertNotNull($closed->closedAt);
    }

    public function testASecondConsultationOnTheSameVisitCanStillBeClosed(): void
    {
        $admissionId = $this->openAdmission('Milou');

        $first  = $this->startConsultation($admissionId);
        $second = $this->startConsultation($admissionId);

        $this->commandBus->dispatch(new CloseConsultation($first, self::VET_ID, null));

        // The visit is already over; closing the other consultation must not
        // blow up on an admission that cannot be closed twice.
        $this->commandBus->dispatch(new CloseConsultation($second, self::VET_ID, null));

        self::assertNotContains($admissionId, $this->activeAdmissionIds());
    }

    /**
     * @return list<string>
     */
    private function activeAdmissionIds(): array
    {
        return array_map(
            static fn (WaitingRoomItemDto $item): string => $item->admissionId,
            $this->admissions->findAllActiveForClinic(self::CLINIC_ID),
        );
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
