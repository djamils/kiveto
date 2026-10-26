<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Clinic\Application\Command;

use App\Context\Clinic\Application\Command\Clinic\UpdateClinicBillingSettings\UpdateClinicBillingSettings;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicLetterhead\UpdateClinicLetterhead;
use App\Context\Clinic\Domain\Exception\InvoicePrefixAlreadyUsedException;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable\LetterheadEmbeddable;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\ValueObject\Siren;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * Lyon and Cassis are two establishments of one company (SIREN 901 122 333):
 * each may number its documents, as long as no number can come out twice.
 */
final class InvoiceNumberingPerSirenTest extends KernelTestCase
{
    use Factories;

    private const string LYON_ID   = '01960000-0000-7000-8000-00000000a101';
    private const string CASSIS_ID = '01960000-0000-7000-8000-00000000a102';

    private CommandBusInterface $commandBus;
    private ClinicRepositoryInterface $clinics;

    protected function setUp(): void
    {
        parent::setUp();

        $commandBus = self::getContainer()->get(CommandBusInterface::class);
        \assert($commandBus instanceof CommandBusInterface);
        $this->commandBus = $commandBus;

        $clinics = self::getContainer()->get(ClinicRepositoryInterface::class);
        \assert($clinics instanceof ClinicRepositoryInterface);
        $this->clinics = $clinics;

        ClinicEntityFactory::new()
            ->withId(self::LYON_ID)
            ->withName('Lyon')
            ->withLetterhead(new LetterheadEmbeddable(siret: '90112233300015', vatNumber: 'FR00901122333'))
            ->withBillingSettings(['invoiceNumberPrefix' => 'LYO', 'creditNotePrefix' => 'AVLYO'])
            ->create()
        ;
        ClinicEntityFactory::new()
            ->withId(self::CASSIS_ID)
            ->withName('Cassis')
            ->withLetterhead(new LetterheadEmbeddable(siret: '90112233300023', vatNumber: 'FR00901122333'))
            ->withBillingSettings(['invoiceNumberPrefix' => 'CAS', 'creditNotePrefix' => 'AVCAS'])
            ->create()
        ;
    }

    public function testCassisCannotNumberItsInvoicesUnderThePrefixOfLyon(): void
    {
        try {
            $this->commandBus->dispatch(new UpdateClinicBillingSettings(clinicId: self::CASSIS_ID, invoiceNumberPrefix: 'lyo', creditNotePrefix: 'AVCAS'));
            self::fail('The prefix of Lyon should have been refused.');
        } catch (InvoicePrefixAlreadyUsedException $exception) {
            self::assertSame('LYO', $exception->prefix);
            self::assertSame(InvoicePrefixAlreadyUsedException::SERIES_INVOICE, $exception->series);
        }

        self::assertSame('CAS', $this->prefixOf(self::CASSIS_ID));
    }

    public function testTheCreditNotePrefixIsCheckedToo(): void
    {
        $this->expectException(InvoicePrefixAlreadyUsedException::class);

        $this->commandBus->dispatch(new UpdateClinicBillingSettings(clinicId: self::CASSIS_ID, invoiceNumberPrefix: 'CAS', creditNotePrefix: 'AVLYO'));
    }

    public function testTwoEstablishmentsLeftOnTheDefaultPrefixesWouldCollide(): void
    {
        $this->commandBus->dispatch(new UpdateClinicBillingSettings(clinicId: self::LYON_ID));

        // Lyon now numbers under « FAC » and « AV »: Cassis may not join it there.
        $this->expectException(InvoicePrefixAlreadyUsedException::class);

        $this->commandBus->dispatch(new UpdateClinicBillingSettings(clinicId: self::CASSIS_ID));
    }

    public function testDistinctPrefixesAreAccepted(): void
    {
        $this->commandBus->dispatch(new UpdateClinicBillingSettings(clinicId: self::CASSIS_ID, invoiceNumberPrefix: 'CASSIS', creditNotePrefix: 'AVCASSIS'));

        self::assertSame('CASSIS', $this->prefixOf(self::CASSIS_ID));
        self::assertSame('LYO', $this->prefixOf(self::LYON_ID));
    }

    public function testAClinicCannotJoinACompanyWhosePrefixesItShares(): void
    {
        $paris = ClinicEntityFactory::new()
            ->withBillingSettings(['invoiceNumberPrefix' => 'LYO', 'creditNotePrefix' => 'AVPAR'])
            ->create()
            ->getId()
            ->toRfc4122()
        ;

        $this->expectException(InvoicePrefixAlreadyUsedException::class);

        $this->commandBus->dispatch(new UpdateClinicLetterhead(clinicId: $paris, siret: '90112233300031'));
    }

    public function testTheOtherEstablishmentsOfTheCompanyAreFoundBySiren(): void
    {
        // Same first nine digits, but not a valid SIRET: not an establishment of the company.
        ClinicEntityFactory::new()->withLetterhead(new LetterheadEmbeddable(siret: '90112233300016'))->create();

        $others = $this->clinics->findOthersWithSiren(Siren::fromInput('901122333'), ClinicId::fromString(self::CASSIS_ID));

        self::assertCount(1, $others);
        self::assertSame(self::LYON_ID, $others[0]->id()->toString());
        self::assertSame([], $this->clinics->findOthersWithSiren(Siren::fromInput('812456903'), ClinicId::fromString(self::CASSIS_ID)));
    }

    public function testAStoredLetterheadWithAnInvalidSiretStillLoads(): void
    {
        $id = ClinicEntityFactory::new()
            ->withLetterhead(new LetterheadEmbeddable(siret: '12345678901234', vatNumber: 'FR41812456903'))
            ->create()
            ->getId()
            ->toRfc4122()
        ;

        $clinic = $this->clinics->findById(ClinicId::fromString($id));

        self::assertNotNull($clinic);
        self::assertSame('12345678901234', $clinic->letterhead()->siret);
        self::assertNull($clinic->letterhead()->siren());
        self::assertNull($clinic->letterhead()->vatNumber());
    }

    private function prefixOf(string $clinicId): ?string
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $entityManager->clear();

        return $this->clinics->findById(ClinicId::fromString($clinicId))?->billingSettings()->invoiceNumberPrefix;
    }
}
