<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Console;

use App\Context\Clinic\Application\Port\ClinicConsoleReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListConsoleClinicsHandler
{
    public function __construct(
        private ClinicConsoleReadRepositoryInterface $console,
    ) {
    }

    /**
     * @return list<ConsoleClinicRow>
     */
    public function __invoke(ListConsoleClinics $query): array
    {
        if (null !== $query->clinicId) {
            $clinic = $this->console->clinic($query->clinicId);

            return null !== $clinic ? [$clinic] : [];
        }

        if (null !== $query->search && '' !== trim($query->search)) {
            return $this->console->searchClinics($query->search, $query->limit);
        }

        return $this->console->clinics();
    }
}
