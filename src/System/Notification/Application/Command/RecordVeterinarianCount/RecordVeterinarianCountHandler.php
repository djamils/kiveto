<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\RecordVeterinarianCount;

use App\System\Notification\Domain\Quota\VeterinarianCountRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordVeterinarianCountHandler
{
    public function __construct(private VeterinarianCountRepositoryInterface $counts)
    {
    }

    public function __invoke(RecordVeterinarianCount $command): void
    {
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $command->day);

        if (false === $day || $day->format('Y-m-d') !== $command->day) {
            throw new \InvalidArgumentException(\sprintf('Invalid day "%s" (Y-m-d expected).', $command->day));
        }

        if ($command->count < 0) {
            throw new \InvalidArgumentException('A veterinarian count cannot be negative.');
        }

        $this->counts->record($command->clinicId, $day, $command->count);
    }
}
