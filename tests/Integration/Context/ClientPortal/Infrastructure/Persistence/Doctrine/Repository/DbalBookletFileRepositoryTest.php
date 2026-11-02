<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Application\Port\BookletFileRepositoryInterface;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity\BookletFileEntity;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalBookletFileRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class DbalBookletFileRepositoryTest extends KernelTestCase
{
    private const string CLINIC  = '01960000-0000-7000-8000-0000000fb201';
    private const string CLINIC2 = '01960000-0000-7000-8000-0000000fb202';
    private const string ANIMAL  = '01960000-0000-7000-8000-0000000fd201';

    public function testTheCurrentBookletOfAnAnimalIsUpserted(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $booklets = new DbalBookletFileRepository($connection);
        $at       = new \DateTimeImmutable('2026-10-29 10:00', new \DateTimeZone('Europe/Paris'));

        self::assertNull($booklets->currentKey(self::CLINIC, self::ANIMAL));

        $booklets->saveCurrent(self::CLINIC, self::ANIMAL, 'client_portal/booklets/a-1.pdf', $at);
        self::assertSame('client_portal/booklets/a-1.pdf', $booklets->currentKey(self::CLINIC, self::ANIMAL));
        self::assertNull($booklets->currentKey(self::CLINIC2, self::ANIMAL), 'another clinic');

        $booklets->saveCurrent(self::CLINIC, self::ANIMAL, 'client_portal/booklets/a-2.pdf', $at->modify('+1 hour'));
        self::assertSame('client_portal/booklets/a-2.pdf', $booklets->currentKey(self::CLINIC, self::ANIMAL));
        self::assertTrue($booklets->isCurrentKey('client_portal/booklets/a-2.pdf'));
        self::assertFalse($booklets->isCurrentKey('client_portal/booklets/a-1.pdf'), 'replaced');
        self::assertSame('2026-10-29 10:00:00', $connection->fetchOne(
            'SELECT updated_at FROM client_portal__booklet_files WHERE animal_id = :animal',
            ['animal' => Uuid::fromString(self::ANIMAL)->toBinary()],
        ), 'one row per animal, in UTC');
    }

    public function testTheTableIsMappedForTheSchemaTools(): void
    {
        $at       = new \DateTimeImmutable('2026-10-29 10:00', new \DateTimeZone('UTC'));
        $booklets = self::getContainer()->get(BookletFileRepositoryInterface::class);
        \assert($booklets instanceof BookletFileRepositoryInterface);
        $booklets->saveCurrent(self::CLINIC, self::ANIMAL, 'client_portal/booklets/a-1.pdf', $at);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $entity = $em->find(BookletFileEntity::class, Uuid::fromString(self::ANIMAL));
        self::assertInstanceOf(BookletFileEntity::class, $entity);
        self::assertSame('client_portal/booklets/a-1.pdf', $entity->storageKey);

        self::assertSame('k', new BookletFileEntity(Uuid::v7(), Uuid::v7(), 'k', $at)->storageKey);
    }
}
