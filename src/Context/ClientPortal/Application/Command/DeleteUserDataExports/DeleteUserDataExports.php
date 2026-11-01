<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\DeleteUserDataExports;

use App\Shared\Application\Bus\CommandInterface;

/** Account deletion: every export of the account and its archive are deleted. */
final readonly class DeleteUserDataExports implements CommandInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
