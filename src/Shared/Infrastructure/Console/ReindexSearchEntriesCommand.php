<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Context\Animal\Application\Search\AnimalSearchEntryData;
use App\Context\Animal\Application\Search\AnimalSearchEntryWriterInterface;
use App\Context\Client\Application\Search\ClientSearchEntryData;
use App\Context\Client\Application\Search\ClientSearchEntryWriterInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:search:reindex',
    description: 'Backfills animal__search_entries and client__search_entries from existing aggregate tables.',
)]
final class ReindexSearchEntriesCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly AnimalSearchEntryWriterInterface $animalWriter,
        private readonly ClientSearchEntryWriterInterface $clientWriter,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Search index re-indexation');

        $this->reindexAnimals($io);
        $this->reindexClients($io);

        $io->success('Re-indexation terminée.');

        return Command::SUCCESS;
    }

    private function reindexAnimals(SymfonyStyle $io): void
    {
        $io->section('Animals');

        $rows = $this->connection->fetchAllAssociative(
            "SELECT
                BIN_TO_UUID(a.id)           AS animal_id,
                BIN_TO_UUID(a.clinic_id)    AS clinic_id,
                a.name                       AS animal_name,
                a.species,
                a.breed_name,
                a.microchip_number,
                a.status,
                BIN_TO_UUID(o.client_id)    AS primary_owner_client_id,
                TRIM(CONCAT(c.first_name, ' ', c.last_name)) AS owner_name,
                cm.value                     AS owner_phone
            FROM animal__animals a
            LEFT JOIN animal__ownerships o
                ON o.animal_id = a.id
                AND o.role     = 'primary'
                AND o.status   = 'active'
            LEFT JOIN client__clients c ON c.id = o.client_id
            LEFT JOIN client__contact_methods cm
                ON cm.client_id  = o.client_id
                AND cm.type      = 'phone'
                AND cm.is_primary = 1",
        );

        $io->progressStart(\count($rows));

        foreach ($rows as $row) {
            \assert(\is_string($row['animal_id']));
            \assert(\is_string($row['clinic_id']));
            \assert(\is_string($row['animal_name']));
            \assert(\is_string($row['species']));
            \assert(\is_string($row['status']));

            $this->animalWriter->upsert(new AnimalSearchEntryData(
                animalId: $row['animal_id'],
                clinicId: $row['clinic_id'],
                animalName: $row['animal_name'],
                species: $row['species'],
                breedName: \is_string($row['breed_name']) && '' !== $row['breed_name']
                    ? $row['breed_name']
                    : null,
                chipNumber: \is_string($row['microchip_number']) && '' !== $row['microchip_number']
                    ? $row['microchip_number']
                    : null,
                ownerName: \is_string($row['owner_name']) && '' !== $row['owner_name']
                    ? $row['owner_name']
                    : null,
                ownerPhone: \is_string($row['owner_phone']) && '' !== $row['owner_phone']
                    ? $row['owner_phone']
                    : null,
                primaryOwnerClientId: \is_string($row['primary_owner_client_id']) && '' !== $row['primary_owner_client_id']
                    ? $row['primary_owner_client_id']
                    : null,
                status: $row['status'],
            ));

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->writeln(\sprintf('  ✓ %d animaux indexés', \count($rows)));
    }

    private function reindexClients(SymfonyStyle $io): void
    {
        $io->section('Clients');

        $rows = $this->connection->fetchAllAssociative(
            "SELECT
                BIN_TO_UUID(c.id)        AS client_id,
                BIN_TO_UUID(c.clinic_id) AS clinic_id,
                c.first_name,
                c.last_name,
                c.status,
                cm_phone.value           AS phone,
                cm_email.value           AS email
            FROM client__clients c
            LEFT JOIN client__contact_methods cm_phone
                ON cm_phone.client_id  = c.id
                AND cm_phone.type      = 'phone'
                AND cm_phone.is_primary = 1
            LEFT JOIN client__contact_methods cm_email
                ON cm_email.client_id  = c.id
                AND cm_email.type      = 'email'
                AND cm_email.is_primary = 1",
        );

        $io->progressStart(\count($rows));

        foreach ($rows as $row) {
            \assert(\is_string($row['client_id']));
            \assert(\is_string($row['clinic_id']));
            \assert(\is_string($row['first_name']));
            \assert(\is_string($row['last_name']));
            \assert(\is_string($row['status']));

            $this->clientWriter->upsert(new ClientSearchEntryData(
                clientId: $row['client_id'],
                clinicId: $row['clinic_id'],
                firstName: $row['first_name'],
                lastName: $row['last_name'],
                phone: \is_string($row['phone']) && '' !== $row['phone']
                    ? $row['phone']
                    : null,
                email: \is_string($row['email']) && '' !== $row['email']
                    ? $row['email']
                    : null,
                status: $row['status'],
            ));

            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->writeln(\sprintf('  ✓ %d clients indexés', \count($rows)));
    }
}
