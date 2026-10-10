<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Application\Port\ClientNoteReadRepositoryInterface;
use App\Context\Client\Domain\ClientNote;
use App\Context\Client\Domain\Repository\ClientNoteRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientNoteId;
use App\Context\Client\Domain\ValueObject\ClientNoteKind;
use App\Context\Client\Domain\ValueObject\ClinicId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineClientNoteRepositoryTest extends KernelTestCase
{
    use Factories;

    private const string CLIENT_ID = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLINIC_ID = '12345678-9abc-def0-1234-56789abcdef0';

    public function testListsTheNotesOfOneClientNewestFirst(): void
    {
        $this->save('0192a000-0000-7000-8000-000000000001', self::CLINIC_ID, self::CLIENT_ID, 'Première note.', '2026-07-22 09:00:00', ClientNoteKind::INFORMATION);
        $this->save('0192a000-0000-7000-8000-000000000002', self::CLINIC_ID, self::CLIENT_ID, 'Litige en cours.', '2026-08-22 09:00:00', ClientNoteKind::ATTENTION);
        // Another client and another clinic must not leak into the list.
        $this->save('0192a000-0000-7000-8000-000000000003', self::CLINIC_ID, '99999999-9999-7999-8999-999999999999', 'Autre client.', '2026-09-01 09:00:00');
        $this->save('0192a000-0000-7000-8000-000000000004', '88888888-8888-7888-8888-888888888888', self::CLIENT_ID, 'Autre clinique.', '2026-09-02 09:00:00');

        $notes = $this->readRepository()->listForClient(
            ClinicId::fromString(self::CLINIC_ID),
            ClientId::fromString(self::CLIENT_ID),
        );

        self::assertCount(2, $notes);

        self::assertSame('0192a000-0000-7000-8000-000000000002', $notes[0]->id);
        self::assertSame('attention', $notes[0]->kind);
        self::assertSame('Litige en cours.', $notes[0]->content);
        self::assertSame('Camille', $notes[0]->authorName);
        self::assertSame('2026-08-22 09:00:00', $notes[0]->createdAt->format('Y-m-d H:i:s'));

        self::assertSame('0192a000-0000-7000-8000-000000000001', $notes[1]->id);
        self::assertSame('information', $notes[1]->kind);
        self::assertSame('Première note.', $notes[1]->content);
    }

    public function testReturnsAnEmptyListWhenTheClientHasNoNote(): void
    {
        self::assertSame([], $this->readRepository()->listForClient(
            ClinicId::fromString(self::CLINIC_ID),
            ClientId::fromString(self::CLIENT_ID),
        ));
    }

    private function save(
        string $id,
        string $clinicId,
        string $clientId,
        string $content,
        string $createdAt,
        ClientNoteKind $kind = ClientNoteKind::INFORMATION,
    ): void {
        $repository = static::getContainer()->get(ClientNoteRepositoryInterface::class);
        \assert($repository instanceof ClientNoteRepositoryInterface);

        $repository->save(ClientNote::add(
            ClientNoteId::fromString($id),
            ClinicId::fromString($clinicId),
            ClientId::fromString($clientId),
            $kind,
            $content,
            'Camille',
            new \DateTimeImmutable($createdAt),
        ));
    }

    private function readRepository(): ClientNoteReadRepositoryInterface
    {
        $readRepository = static::getContainer()->get(ClientNoteReadRepositoryInterface::class);
        \assert($readRepository instanceof ClientNoteReadRepositoryInterface);

        return $readRepository;
    }
}
