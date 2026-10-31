<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalBookletFileRepository;
use Doctrine\DBAL\Connection;
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
        self::assertSame('2026-10-29 10:00:00', $connection->fetchOne(
            'SELECT updated_at FROM client_portal__booklet_files WHERE animal_id = :animal',
            ['animal' => Uuid::fromString(self::ANIMAL)->toBinary()],
        ), 'one row per animal, in UTC');
    }
}
