<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\ListClientNotes;

use App\Context\Client\Application\Port\ClientNoteReadRepositoryInterface;
use App\Context\Client\Application\Query\ListClientNotes\ClientNoteView;
use App\Context\Client\Application\Query\ListClientNotes\ListClientNotes;
use App\Context\Client\Application\Query\ListClientNotes\ListClientNotesHandler;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ListClientNotesHandlerTest extends TestCase
{
    public function testAsksTheReadRepositoryForTheClientOfTheClinic(): void
    {
        $clinicId = '12345678-9abc-def0-1234-56789abcdef0';
        $clientId = '01234567-89ab-cdef-0123-456789abcdef';
        $at       = new \DateTimeImmutable('2026-07-22 09:15:00');
        $view     = new ClientNoteView('0192a000-0000-7000-8000-000000000001', 'attention', 'Litige en cours.', 'Camille', $at);

        $readRepository = $this->createMock(ClientNoteReadRepositoryInterface::class);
        $readRepository->expects(self::once())
            ->method('listForClient')
            ->with(
                self::callback(static fn (ClinicId $id): bool => $clinicId === $id->toString()),
                self::callback(static fn (ClientId $id): bool => $clientId === $id->toString()),
            )
            ->willReturn([$view])
        ;

        $result = (new ListClientNotesHandler($readRepository))(new ListClientNotes($clinicId, $clientId));

        self::assertSame([$view], $result);
        self::assertSame('attention', $result[0]->kind);
        self::assertSame('Litige en cours.', $result[0]->content);
        self::assertSame('Camille', $result[0]->authorName);
        self::assertSame($at, $result[0]->createdAt);
    }
}
