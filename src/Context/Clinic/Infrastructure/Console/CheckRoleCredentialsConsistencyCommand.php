<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Console;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicMembershipEntity;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\StaffProfileEntity;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:clinic:staff:check-role-credentials-consistency',
    description: 'Scans for StaffProfile rows with credentials whose linked membership role cannot hold them.',
)]
final class CheckRoleCredentialsConsistencyCommand extends Command
{
    private string $profileTableName;
    private string $membershipTableName;

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        EntityManagerInterface $entityManager,
    ) {
        parent::__construct();

        $this->profileTableName    = $entityManager->getClassMetadata(StaffProfileEntity::class)->getTableName();
        $this->membershipTableName = $entityManager->getClassMetadata(ClinicMembershipEntity::class)->getTableName();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sql = \sprintf(
            <<<'SQL'
            SELECT
                BIN_TO_UUID(p.membership_id) AS membership_id,
                BIN_TO_UUID(p.id) AS staff_profile_id,
                m.role
            FROM %s p
            INNER JOIN %s m ON m.id = p.membership_id
            WHERE p.registration_number IS NOT NULL
        SQL,
            $this->profileTableName,
            $this->membershipTableName,
        );

        $rows      = $this->connection->fetchAllAssociative($sql);
        $offenders = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['role']) || \is_int($row['role']));
            \assert(\is_string($row['membership_id']));
            \assert(\is_string($row['staff_profile_id']));

            $role = ClinicMemberRole::from((string) $row['role']);

            if (!$role->canHoldVeterinaryCredentials()) {
                $offenders[] = $row;
            }
        }

        if ([] === $offenders) {
            $output->writeln('OK: No role/credentials inconsistencies found.');

            return Command::SUCCESS;
        }

        foreach ($offenders as $offender) {
            $line = \sprintf(
                '%s %s %s',
                $offender['membership_id'],
                $offender['staff_profile_id'],
                $offender['role'],
            );

            $output->writeln($line);

            $this->logger->warning('Role/credentials inconsistency detected.', [
                'membership_id'    => $offender['membership_id'],
                'staff_profile_id' => $offender['staff_profile_id'],
                'role'             => $offender['role'],
            ]);
        }

        return Command::FAILURE;
    }
}
