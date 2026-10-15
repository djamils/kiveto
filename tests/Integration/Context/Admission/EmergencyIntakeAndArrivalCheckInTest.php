<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Admission;

use App\Context\Admission\Application\Command\OpenEmergencyAdmission\OpenEmergencyAdmission;
use App\Context\Admission\Application\Command\OpenEmergencyAdmission\OpenEmergencyAdmissionHandler;
use App\Context\Admission\Application\Command\RecordArrivalCheckIn\RecordArrivalCheckIn;
use App\Context\Admission\Application\Command\RecordArrivalCheckIn\RecordArrivalCheckInHandler;
use App\Context\Admission\Application\Port\AdmissionReadRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The flow board's writes end to end through the database: an emergency
 * brought in by a third party with an unknown chip, then what the desk
 * notes at arrival on an admission already saved.
 */
final class EmergencyIntakeAndArrivalCheckInTest extends KernelTestCase
{
    private const string CLINIC_ID = '01950000-0000-7000-0000-0000000000b1';

    public function testTheIntakeAndTheCheckInAreKeptOnTheAdmission(): void
    {
        $open = self::getContainer()->get(OpenEmergencyAdmissionHandler::class);
        \assert($open instanceof OpenEmergencyAdmissionHandler);

        $admissionId = $open(new OpenEmergencyAdmission(
            clinicId: self::CLINIC_ID,
            intakeChannel: 'emergency_by_third_party',
            triageLevel: 'emergency',
            provisionalLabel: 'Urgence 10:28',
            observedSpecies: 'Chat',
            presenterName: 'M. Garcia',
            presenterPhone: '+33645127790',
            presenterRole: 'third_party',
            triageNotes: 'Trauma / AVP',
            scannedMicrochip: '250268500112847',
        ));

        $record = self::getContainer()->get(RecordArrivalCheckInHandler::class);
        \assert($record instanceof RecordArrivalCheckInHandler);
        $record(new RecordArrivalCheckIn(
            clinicId: self::CLINIC_ID,
            admissionId: $admissionId,
            ongoingTreatment: true,
            weightGrams: 3800,
        ));

        $reader = self::getContainer()->get(AdmissionReadRepositoryInterface::class);
        \assert($reader instanceof AdmissionReadRepositoryInterface);
        $items = $reader->findAllActiveForClinic(self::CLINIC_ID);

        self::assertCount(1, $items);
        self::assertSame($admissionId, $items[0]->admissionId);
        self::assertSame('third_party', $items[0]->presenterRole);
        self::assertSame('M. Garcia', $items[0]->presenterName);
        self::assertSame('250268500112847', $items[0]->scannedMicrochip);
        self::assertSame('Chat', $items[0]->observedSpecies);
        self::assertNotNull($items[0]->arrivalCheckIn);
        self::assertTrue($items[0]->arrivalCheckIn->ongoingTreatment);
        self::assertSame(3800, $items[0]->arrivalCheckIn->weightGrams);
    }
}
