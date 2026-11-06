<?php

declare(strict_types=1);

namespace App\System\Audit\Domain\ValueObject;

/**
 * Who did it: a member of the Kiveto team (back office), a member of a
 * clinic's staff, the platform itself (a scheduled job, an import), or an
 * unidentified caller (a refused sign-in).
 */
enum ActorKind: string
{
    case KIVETO = 'kiveto';

    case STAFF = 'staff';

    case SYSTEM = 'system';

    case UNKNOWN = 'unknown';
}
