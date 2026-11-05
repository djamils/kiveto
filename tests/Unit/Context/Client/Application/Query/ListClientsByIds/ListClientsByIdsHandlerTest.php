<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\ListClientsByIds;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\ListClientsByIds\ListClientsByIds;
use App\Context\Client\Application\Query\ListClientsByIds\ListClientsByIdsHandler;
use App\Context\Client\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class ListClientsByIdsHandlerTest extends TestCase
{
    private const string CLINIC = '12345678-9abc-def0-1234-56789abcdef0';
    private const string SOPHIE = '0192d000-0000-7000-8000-000000000001';
    private const string PAUL   = '0192d000-0000-7000-8000-000000000002';

    public function testNoIdReadsNothing(): void
    {
        $repository = $this->createMock(ClientReadRepositoryInterface::class);
        $repository->expects(self::never())->method('findByIds');

        self::assertSame([], new ListClientsByIdsHandler($repository)(new ListClientsByIds(self::CLINIC, [])));
    }

    public function testTheFilesAreReadOnceEachAndKeyedById(): void
    {
        $sophie = self::client(self::SOPHIE, 'Sophie');
        $paul   = self::client(self::PAUL, 'Paul');

        $repository = $this->createMock(ClientReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findByIds')
            ->with(self::callback(static fn (ClinicId $id): bool => self::CLINIC === $id->toString()), self::identicalTo([self::SOPHIE, self::PAUL]))
            ->willReturn([$paul, $sophie])
        ;

        self::assertSame(
            [self::PAUL => $paul, self::SOPHIE => $sophie],
            new ListClientsByIdsHandler($repository)(new ListClientsByIds(self::CLINIC, [self::SOPHIE, self::PAUL, self::SOPHIE])),
        );
    }

    private static function client(string $id, string $firstName): ClientView
    {
        return new ClientView($id, self::CLINIC, $firstName, 'Martin', 'active', [], null, '2026-01-01T00:00:00+00:00', '2026-01-01T00:00:00+00:00');
    }
}
