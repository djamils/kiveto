<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Clinic\Application\Command;

use App\Context\Clinic\Application\Command\Clinic\UpdateClinicBillingSettings\UpdateClinicBillingSettings;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicLetterhead\UpdateClinicLetterhead;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicOpeningHours\UpdateClinicOpeningHours;
use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * What a clinic says about itself has to survive being said.
 */
final class ClinicSettingsPersistenceTest extends KernelTestCase
{
    use Factories;

    private CommandBusInterface $commandBus;
    private ClinicRepositoryInterface $clinics;
    private string $clinicId;

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();

        $commandBus = $container->get(CommandBusInterface::class);
        \assert($commandBus instanceof CommandBusInterface);
        $this->commandBus = $commandBus;

        $clinics = $container->get(ClinicRepositoryInterface::class);
        \assert($clinics instanceof ClinicRepositoryInterface);
        $this->clinics = $clinics;

        $this->clinicId = ClinicEntityFactory::createOne()->getId()->toRfc4122();
    }

    public function testTheLetterheadSurvivesTheSave(): void
    {
        $this->commandBus->dispatch(new UpdateClinicLetterhead(
            clinicId: $this->clinicId,
            legalName: 'Clinique des Lilas SELARL',
            siret: '12345678901234',
            streetLine1: '12 rue des Lilas',
            city: 'Paris',
            countryCode: 'FR',
            postalCode: '75018',
        ));

        $letterhead = $this->reload()->letterhead();

        self::assertSame('Clinique des Lilas SELARL', $letterhead->legalName);
        self::assertSame('12345678901234', $letterhead->siret);
        self::assertNotNull($letterhead->address);
        self::assertSame('Paris', $letterhead->address->city);
    }

    public function testTheOpeningHoursComeBackAsTheyWereSaid(): void
    {
        $this->commandBus->dispatch(new UpdateClinicOpeningHours(
            clinicId: $this->clinicId,
            week: [
                1 => [['opensAt' => '09:00', 'closesAt' => '12:30'], ['opensAt' => '14:00', 'closesAt' => '19:00']],
                6 => [['opensAt' => '09:00', 'closesAt' => '13:00']],
            ],
            closures: [['date' => '2026-12-25', 'reason' => 'Noël']],
        ));

        $hours = $this->reload()->openingHours();

        self::assertCount(2, $hours->slotsOn(1));
        self::assertSame('09:00', $hours->slotsOn(1)[0]->opensAt);
        self::assertSame('19:00', $hours->slotsOn(1)[1]->closesAt);
        self::assertTrue($hours->isClosedOn(7));
        self::assertCount(1, $hours->closures);
        self::assertSame('Noël', $hours->closures[0]->reason);
    }

    public function testTheBillingSettingsComeBackAsTheyWereSaid(): void
    {
        $this->commandBus->dispatch(new UpdateClinicBillingSettings(
            clinicId: $this->clinicId,
            streetLine1: '3 avenue du Siège',
            postalCode: '69002',
            city: 'Lyon',
            countryCode: 'FR',
            invoiceNumberPrefix: 'fac-2026',
            paymentTermsDays: 30,
            iban: 'FR76 3000 6000 0112 3456 7890 189',
            bic: 'AGRIFRPP',
            invoiceFooterNote: 'Règlement à réception.',
        ));

        $settings = $this->reload()->billingSettings();

        self::assertNotNull($settings->billingAddress);
        self::assertSame('Lyon', $settings->billingAddress->city);
        // The prefix is stored the way it prints, whatever the case it was typed in.
        self::assertSame('FAC-2026', $settings->invoiceNumberPrefix);
        self::assertSame(30, $settings->paymentTermsDays);
        // Spaces are how an IBAN is written, not how it is stored.
        self::assertSame('FR7630006000011234567890189', $settings->iban);
        self::assertSame('AGRIFRPP', $settings->bic);
        self::assertSame('Règlement à réception.', $settings->invoiceFooterNote);
    }

    public function testASlotThatClosesBeforeItOpensIsRefused(): void
    {
        $this->expectException(\Throwable::class);

        $this->commandBus->dispatch(new UpdateClinicOpeningHours(
            clinicId: $this->clinicId,
            week: [1 => [['opensAt' => '18:00', 'closesAt' => '09:00']]],
        ));
    }

    private function reload(): Clinic
    {
        // Read it back from the database, not from the identity map: the point
        // is to prove it was written.
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $entityManager->clear();

        $clinic = $this->clinics->findById(ClinicId::fromString($this->clinicId));

        self::assertNotNull($clinic);

        return $clinic;
    }
}
