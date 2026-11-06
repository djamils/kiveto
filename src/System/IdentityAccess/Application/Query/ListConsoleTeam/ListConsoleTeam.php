<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\ListConsoleTeam;

use App\Shared\Application\Bus\QueryInterface;

/**
 * « Équipe »: every member of the Kiveto team (active, invited, disabled)
 * with their role. Answers a list of BackofficeProfileView.
 */
final readonly class ListConsoleTeam implements QueryInterface
{
}
