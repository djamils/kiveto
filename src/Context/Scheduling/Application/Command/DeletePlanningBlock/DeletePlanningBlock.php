<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\DeletePlanningBlock;

use App\Shared\Application\Bus\CommandInterface;

final readonly class DeletePlanningBlock implements CommandInterface
{
    public function __construct(
        public string $blockId,
        public string $clinicId,
    ) {
    }
}
