<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Console;

use App\Context\Clinic\Application\Port\ClinicConsoleReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListConsoleMembersHandler
{
    public function __construct(
        private ClinicConsoleReadRepositoryInterface $console,
    ) {
    }

    /**
     * @return list<ConsoleMemberRow>
     */
    public function __invoke(ListConsoleMembers $query): array
    {
        return $this->console->members($query->clinicId, $query->userId);
    }
}
