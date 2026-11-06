<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\ListBackofficeProfiles;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The active members of the Kiveto team with their console settings (who
 * to e-mail when an import fails, when an invitation expires).
 */
final readonly class ListBackofficeProfiles implements QueryInterface
{
}
