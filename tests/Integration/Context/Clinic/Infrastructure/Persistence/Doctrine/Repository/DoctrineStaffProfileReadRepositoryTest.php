<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Application\Port\StaffProfileReadRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Clinic\Factory\StaffProfileEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineStaffProfileReadRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testBatchQueryReturnsCorrectMapForExistingProfiles(): void
    {
        $m1 = ClinicMembershipEntityFactory::new()->asVeterinary()->create();
        $m2 = ClinicMembershipEntityFactory::new()->asVeterinary()->create();

        $id1 = $m1->getId()->toRfc4122();
        $id2 = $m2->getId()->toRfc4122();

        $map = $this->readRepo()->findByMembershipIds([$id1, $id2]);

        self::assertCount(2, $map);
        self::assertArrayHasKey($id1, $map);
        self::assertArrayHasKey($id2, $map);
        self::assertSame($id1, $map[$id1]->membershipId);
        self::assertSame($id2, $map[$id2]->membershipId);
    }

    public function testCarriesTheNamesThePhoneAndTheCredentials(): void
    {
        $vet       = ClinicMembershipEntityFactory::new()->asManager()->create();
        $reception = ClinicMembershipEntityFactory::new()->asManager()->create();

        StaffProfileEntityFactory::new()
            ->forMembership($vet->getId())
            ->withVeterinaryCredentials('FR-24871', signatureImageKey: 'signatures/lambert.png')
            ->create(['firstName' => 'Claire', 'lastName' => 'Lambert', 'phoneNumber' => '+33612345678'])
        ;
        StaffProfileEntityFactory::new()
            ->forMembership($reception->getId())
            ->withoutCredentials()
            ->create(['firstName' => 'Sarah', 'lastName' => 'Benali'])
        ;

        $map = $this->readRepo()->findByMembershipIds([$vet->getId()->toRfc4122(), $reception->getId()->toRfc4122()]);

        $claire = $map[$vet->getId()->toRfc4122()];
        self::assertSame('Claire', $claire->firstName);
        self::assertSame('Lambert', $claire->lastName);
        self::assertSame('+33612345678', $claire->phone);
        self::assertSame('FR-24871', $claire->registrationNumber);
        self::assertTrue($claire->hasSignature);

        self::assertSame('Lambert', $this->readRepo()->findByMembershipId($vet->getId()->toRfc4122())?->lastName);
        self::assertNull($this->readRepo()->findByMembershipId('01912345-6789-7abc-8def-aaaaaaaaaaaa'));

        $sarah = $map[$reception->getId()->toRfc4122()];
        self::assertSame('Benali', $sarah->lastName);
        self::assertNull($sarah->phone);
        self::assertNull($sarah->registrationNumber);
        self::assertFalse($sarah->hasSignature);
    }

    public function testEmptyInputReturnsEmptyMap(): void
    {
        $map = $this->readRepo()->findByMembershipIds([]);

        self::assertSame([], $map);
    }

    public function testMissingMembershipIsAbsentFromMap(): void
    {
        $nonExistentId = '01912345-6789-7abc-8def-aaaaaaaaaaaa';

        $map = $this->readRepo()->findByMembershipIds([$nonExistentId]);

        self::assertSame([], $map);
    }

    public function testHasVeterinaryCredentialsForReturnsTrueWhenCredentialsExist(): void
    {
        $membership   = ClinicMembershipEntityFactory::new()->asVeterinary()->create();
        $membershipId = ClinicMembershipId::fromString($membership->getId()->toRfc4122());

        // asVeterinary afterPersist creates a profile with credentials (asDoctor)
        $hasCredentials = $this->readRepo()->hasVeterinaryCredentialsFor($membershipId);

        self::assertTrue($hasCredentials);
    }

    public function testHasVeterinaryCredentialsForReturnsFalseWhenNoProfile(): void
    {
        $membership   = ClinicMembershipEntityFactory::new()->asManager()->create();
        $membershipId = ClinicMembershipId::fromString($membership->getId()->toRfc4122());

        $hasCredentials = $this->readRepo()->hasVeterinaryCredentialsFor($membershipId);

        self::assertFalse($hasCredentials);
    }

    private function readRepo(): StaffProfileReadRepositoryInterface
    {
        $repo = self::getContainer()->get(StaffProfileReadRepositoryInterface::class);
        \assert($repo instanceof StaffProfileReadRepositoryInterface);

        return $repo;
    }
}
