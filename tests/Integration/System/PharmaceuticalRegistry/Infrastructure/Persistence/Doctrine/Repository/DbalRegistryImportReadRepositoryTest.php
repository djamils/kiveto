<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Repository;

use App\Fixtures\System\PharmaceuticalRegistry\Factory\AuthorizationEntityFactory;
use App\System\PharmaceuticalRegistry\Application\Port\RegistryImportReadRepositoryInterface;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryDiffLine;
use App\System\PharmaceuticalRegistry\Application\Query\Console\RegistryImportView;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DbalRegistryImportReadRepositoryTest extends KernelTestCase
{
    use Factories;

    private const string APPLIED   = '01960000-0000-7000-8000-0000000a3001';
    private const string FAILED    = '01960000-0000-7000-8000-0000000a3002';
    private const string ELSEWHERE = '01960000-0000-7000-8000-0000000a3003';

    protected function setUp(): void
    {
        $connection = $this->connection();
        $connection->executeStatement('DELETE FROM pharmaceutical_registry__snapshot_entries');
        $connection->executeStatement('DELETE FROM pharmaceutical_registry__snapshots');

        $this->snapshot(self::APPLIED, 'ANMV', 'APPLIED', '2026-10-01 08:00:00', '2026-10-01 08:00:52', null);
        $this->snapshot(self::FAILED, 'ANMV', 'FAILED', '2026-10-08 08:00:00', null, 'Fichier tronqué');
        $this->snapshot(self::ELSEWHERE, 'EMA_UPD', 'APPLIED', '2026-10-09 08:00:00', '2026-10-09 08:01:00', null);

        $dto = ['commercialName' => 'Méloxicam 1,5 mg/ml', 'holderLaboratoryLabel' => 'Vetoquinol'];
        $this->entry(self::APPLIED, 'FR/V/0000002', 'CREATE', $dto, null);
        $this->entry(self::APPLIED, 'FR/V/0000001', 'CREATE', ['commercialName' => 'Amoxicilline 250 mg'], null);
        $this->entry(self::APPLIED, 'FR/V/0000005', 'UPDATE', $dto, '["holderLaboratoryLabel", "commercialName"]');
        $this->entry(self::APPLIED, 'FR/V/0000004', 'UPDATE', $dto, '{"status": {"from": 1, "to": 2}, "atcVetCode": {"from": "QJ01", "to": "QJ01CA04"}}');
        $this->entry(self::APPLIED, 'FR/V/0000006', 'UPDATE', $dto, '[3, "", "atcVetCode"]');
        $this->entry(self::APPLIED, 'FR/V/0000007', 'UPDATE', $dto, '"not a list"');
        $this->entry(self::APPLIED, 'FR/V/0000009', 'WITHDRAW', ['authorityIdentifier' => 'FR/V/0000009', 'commercialName' => 42], null);
        $this->entry(self::APPLIED, 'FR/V/0000008', null, $dto, null);
        $this->entry(self::FAILED, 'FR/V/0000010', 'CREATE', $dto, null);
    }

    public function testTheImportsOfASourceAreListedNewestFirstWithTheirCounts(): void
    {
        $imports = $this->imports()->imports('ANMV', 10);

        self::assertSame([self::FAILED, self::APPLIED], array_map(static fn (RegistryImportView $import): string => $import->id, $imports), 'the other source is left out');
        self::assertSame(['FAILED', '2026-10-08 08:00:00', null, 'Fichier tronqué', 1, 0, 0], self::fields($imports[0]));
        self::assertSame(['APPLIED', '2026-10-01 08:00:00', '2026-10-01 08:00:52', null, 2, 4, 1], self::fields($imports[1]));
        self::assertSame('UTC', $imports[1]->downloadedAt->getTimezone()->getName());

        self::assertSame([self::FAILED], array_map(static fn (RegistryImportView $import): string => $import->id, $this->imports()->imports('ANMV', 0)), 'at least one');
    }

    public function testOneImportIsReadByItsId(): void
    {
        $import = $this->imports()->import(self::APPLIED);

        self::assertNotNull($import);
        self::assertSame(['APPLIED', '2026-10-01 08:00:00', '2026-10-01 08:00:52', null, 2, 4, 1], self::fields($import));
        self::assertNull($this->imports()->import('01960000-0000-7000-8000-0000000a30ff'));
    }

    public function testTheChangesAreListedWithdrawnThenChangedThenAdded(): void
    {
        $lines = $this->imports()->diff(self::APPLIED, null, 0, 50);

        self::assertSame([
            ['FR/V/0000009', 'WITHDRAW', null, null, []],
            ['FR/V/0000004', 'UPDATE', 'Méloxicam 1,5 mg/ml', 'Vetoquinol', ['status', 'atcVetCode']],
            ['FR/V/0000005', 'UPDATE', 'Méloxicam 1,5 mg/ml', 'Vetoquinol', ['holderLaboratoryLabel', 'commercialName']],
            ['FR/V/0000006', 'UPDATE', 'Méloxicam 1,5 mg/ml', 'Vetoquinol', ['atcVetCode']],
            ['FR/V/0000007', 'UPDATE', 'Méloxicam 1,5 mg/ml', 'Vetoquinol', []],
            ['FR/V/0000001', 'CREATE', 'Amoxicilline 250 mg', null, []],
            ['FR/V/0000002', 'CREATE', 'Méloxicam 1,5 mg/ml', 'Vetoquinol', []],
        ], self::lines($lines), 'an unchanged entry is not a change');
        self::assertSame(7, $this->imports()->countDiff(self::APPLIED, null));
    }

    public function testTheChangesOfOneKindArePaged(): void
    {
        self::assertSame(['FR/V/0000001', 'FR/V/0000002'], array_map(static fn (RegistryDiffLine $line): string => $line->identifier, $this->imports()->diff(self::APPLIED, 'CREATE', 0, 10)));
        self::assertSame(['FR/V/0000005', 'FR/V/0000006'], array_map(static fn (RegistryDiffLine $line): string => $line->identifier, $this->imports()->diff(self::APPLIED, 'UPDATE', 1, 2)));
        self::assertSame(['FR/V/0000009'], array_map(static fn (RegistryDiffLine $line): string => $line->identifier, $this->imports()->diff(self::APPLIED, null, -3, 0)), 'from the first, at least one');
        self::assertSame([4, 1, 0], [
            $this->imports()->countDiff(self::APPLIED, 'UPDATE'),
            $this->imports()->countDiff(self::APPLIED, 'WITHDRAW'),
            $this->imports()->countDiff(self::ELSEWHERE, null),
        ]);
    }

    public function testTheAuthorisationsOfTheRegistryAreCounted(): void
    {
        $before = $this->imports()->authorizationCount();

        AuthorizationEntityFactory::createOne(['commercialName' => 'Zzquel 3,6 mg']);
        AuthorizationEntityFactory::createOne(['commercialName' => 'Zzquel 16 mg']);

        self::assertSame($before + 2, $this->imports()->authorizationCount());
    }

    /**
     * @return list<mixed>
     */
    private static function fields(RegistryImportView $import): array
    {
        return [
            $import->status, $import->downloadedAt->format('Y-m-d H:i:s'), $import->appliedAt?->format('Y-m-d H:i:s'), $import->errorMessage,
            $import->created, $import->updated, $import->withdrawn,
        ];
    }

    /**
     * @param list<RegistryDiffLine> $lines
     *
     * @return list<array{string, string, ?string, ?string, list<string>}>
     */
    private static function lines(array $lines): array
    {
        return array_map(static fn (RegistryDiffLine $line): array => [$line->identifier, $line->kind, $line->name, $line->laboratory, $line->changedFields], $lines);
    }

    private function snapshot(string $id, string $source, string $status, string $downloadedAt, ?string $appliedAt, ?string $error): void
    {
        $this->connection()->insert('pharmaceutical_registry__snapshots', [
            'id'            => Uuid::fromString($id)->toBinary(),
            'source'        => $source,
            'status'        => $status,
            'downloaded_at' => $downloadedAt,
            'applied_at'    => $appliedAt,
            'error_message' => $error,
        ]);
    }

    /**
     * @param array<string, mixed> $dto
     */
    private function entry(string $snapshotId, string $identifier, ?string $kind, array $dto, ?string $changes): void
    {
        $this->connection()->insert('pharmaceutical_registry__snapshot_entries', [
            'id'                   => Uuid::v7()->toBinary(),
            'snapshot_id'          => Uuid::fromString($snapshotId)->toBinary(),
            'authority_identifier' => $identifier,
            'content_hash'         => hash('sha256', $snapshotId . $identifier),
            'raw_dto'              => json_encode($dto, \JSON_THROW_ON_ERROR),
            'diff_kind'            => $kind,
            'changes'              => $changes,
        ]);
    }

    private function imports(): RegistryImportReadRepositoryInterface
    {
        $repository = self::getContainer()->get(RegistryImportReadRepositoryInterface::class);
        \assert($repository instanceof RegistryImportReadRepositoryInterface);

        return $repository;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
