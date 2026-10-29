<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity\OwnerProfileEntity;
use App\Shared\Domain\ValueObject\PostalAddress;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalOwnerProfileRepositoryTest extends KernelTestCase
{
    private const string USER  = '01960000-0000-7000-8000-0000000c2e01';
    private const string USER2 = '01960000-0000-7000-8000-0000000c2e02';

    public function testAProfileIsReadBackAndOnlyItsTermsMove(): void
    {
        $profiles = $this->profiles();
        $created  = new \DateTimeImmutable('2026-10-29 10:00', new \DateTimeZone('Europe/Paris'));
        $profiles->save(OwnerProfile::createFromClient(self::USER, 'Sophie', 'Martin', PostalAddress::create('12 rue des Lilas', 'Lyon', 'FR', 'Bât. B', '69003'), 'v1', $created));

        $found = $profiles->findByUserId(self::USER);
        self::assertNotNull($found);
        self::assertSame([self::USER, 'Sophie', 'Martin', '12 rue des Lilas', 'Bât. B', '69003', 'Lyon', 'FR', 'v1', '2026-10-29 09:00', '2026-10-29 09:00', 'UTC'], [
            $found->userId(), $found->firstName(), $found->lastName(), $found->address()?->streetLine1, $found->address()?->streetLine2, $found->address()?->postalCode, $found->address()?->city, $found->address()?->countryCode,
            $found->termsVersion(), $found->termsAcceptedAt()->format('Y-m-d H:i'), $found->createdAt()->format('Y-m-d H:i'), $found->createdAt()->getTimezone()->getName(),
        ]);

        // A second acceptance never overwrites the identity, only the terms.
        $profiles->save(OwnerProfile::createFromClient(self::USER, 'Paul', 'Durand', null, 'v2', $created->modify('+1 month')));
        $again = $profiles->findByUserId(self::USER);
        self::assertSame(['Sophie', 'Lyon', 'v2', '2026-11-29 09:00', '2026-10-29 09:00'], [
            $again?->firstName(), $again?->address()?->city, $again?->termsVersion(), $again?->termsAcceptedAt()->format('Y-m-d H:i'), $again?->createdAt()->format('Y-m-d H:i'),
        ]);

        $profiles->save(OwnerProfile::createFromClient(self::USER2, 'Paul', 'Durand', null, 'v1', $created));
        self::assertNull($profiles->findByUserId(self::USER2)?->address());
        self::assertNull($profiles->findByUserId('01960000-0000-7000-8000-0000000c2e09'));
    }

    public function testTheTableIsMappedForTheSchemaTools(): void
    {
        $this->profiles()->save(OwnerProfile::createFromClient(self::USER, 'Sophie', 'Martin', null, 'v1', new \DateTimeImmutable()));

        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $entity = $em->find(OwnerProfileEntity::class, Uuid::fromString(self::USER));
        self::assertInstanceOf(OwnerProfileEntity::class, $entity);
        self::assertSame(['Sophie', null], [$entity->firstName, $entity->addressCity]);

        $built = new OwnerProfileEntity(Uuid::v7(), 'A', 'B', null, null, null, null, null, 'v1', new \DateTimeImmutable(), new \DateTimeImmutable());
        self::assertSame('v1', $built->termsVersion);
    }

    private function profiles(): OwnerProfileRepositoryInterface
    {
        $repository = self::getContainer()->get(OwnerProfileRepositoryInterface::class);
        \assert($repository instanceof OwnerProfileRepositoryInterface);

        return $repository;
    }
}
