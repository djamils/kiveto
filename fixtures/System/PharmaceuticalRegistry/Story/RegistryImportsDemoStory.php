<?php

declare(strict_types=1);

namespace App\Fixtures\System\PharmaceuticalRegistry\Story;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * The weekly imports of the ANMV registry as the console shows them (G1,
 * G3, G4): four Tuesdays, one of them failed and done again the same day.
 * Only the import journal is seeded (snapshots and their changed entries):
 * the registry itself comes from the medication stories.
 */
final class RegistryImportsDemoStory extends Story
{
    private const array PRODUCTS = [
        ['Méloxicam 1,5 mg/ml suspension buvable chien', 'Vetoquinol'],
        ['Amoxicilline 250 mg comprimés chien et chat', 'Virbac'],
        ['Maropitant 10 mg/ml solution injectable', 'Zoetis'],
        ['Robénacoxib 6 mg comprimés chat', 'Elanco'],
        ['Prednisolone 5 mg comprimés chien et chat', 'Dechra'],
        ['Fipronil 50 mg spot-on chat', 'Boehringer Ingelheim'],
        ['Milbémycine oxime + praziquantel comprimés chien', 'Ceva'],
        ['Céfovécine 80 mg/ml poudre et solvant', 'Zoetis'],
        ['Kétamine 100 mg/ml solution injectable', 'Axience'],
        ['Oclacitinib 16 mg comprimés chien', 'Zoetis'],
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function build(): void
    {
        $tuesday = (new \DateTimeImmutable('last tuesday 07:10', new \DateTimeZone('Europe/Paris')))->setTimezone(new \DateTimeZone('UTC'));

        $this->snapshot($tuesday->modify('-21 days'), 'APPLIED', 52, ['CREATE' => 5, 'UPDATE' => 19, 'WITHDRAW' => 2]);
        $this->snapshot($tuesday->modify('-14 days'), 'FAILED', null, [], 'Fichier tronqué : balise <medicament> non fermée ligne 48 213.');
        $this->snapshot($tuesday->modify('-14 days +2 hours 36 minutes'), 'APPLIED', 41, ['CREATE' => 8, 'UPDATE' => 15]);
        $this->snapshot($tuesday->modify('-7 days'), 'APPLIED', 44, ['CREATE' => 3, 'UPDATE' => 21, 'WITHDRAW' => 1]);
        $this->snapshot($tuesday->modify('+2 minutes'), 'APPLIED', 48, ['CREATE' => 12, 'UPDATE' => 37, 'WITHDRAW' => 4]);
    }

    /**
     * @param array<string, int> $changes
     */
    private function snapshot(\DateTimeImmutable $at, string $status, ?int $seconds, array $changes, ?string $error = null): void
    {
        $id = Uuid::v7();
        $this->connection->insert('pharmaceutical_registry__snapshots', [
            'id'            => $id->toBinary(),
            'source'        => 'ANMV',
            'status'        => $status,
            'downloaded_at' => $at->format('Y-m-d H:i:s'),
            'applied_at'    => null !== $seconds ? $at->modify(\sprintf('+%d seconds', $seconds))->format('Y-m-d H:i:s') : null,
            'error_message' => $error,
        ]);

        $n = 0;
        foreach ($changes as $kind => $count) {
            for ($i = 0; $i < $count; ++$i, ++$n) {
                [$name, $laboratory] = self::PRODUCTS[$n % \count(self::PRODUCTS)];
                $identifier          = \sprintf('FR/V/%07d %d/%04d', 1000000 + $n * 37, 1 + $n % 9, 2010 + $n % 15);
                $this->connection->insert('pharmaceutical_registry__snapshot_entries', [
                    'id'                   => Uuid::v7()->toBinary(),
                    'snapshot_id'          => $id->toBinary(),
                    'authority_identifier' => $identifier,
                    'content_hash'         => hash('sha256', $identifier . $at->format('c')),
                    'raw_dto'              => json_encode(['authorityIdentifier' => $identifier, 'commercialName' => $name, 'holderLaboratoryLabel' => $laboratory], \JSON_THROW_ON_ERROR),
                    'diff_kind'            => $kind,
                    'changes'              => 'UPDATE' === $kind ? json_encode(['holderLaboratoryLabel'], \JSON_THROW_ON_ERROR) : null,
                ]);
            }
        }
    }
}
