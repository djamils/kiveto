<?php

declare(strict_types=1);

namespace App\System\Audit\Domain\ValueObject;

/**
 * The filter chips of the audit log: sign-ins, clinics, users, support
 * sessions, features, the ANMV registry.
 */
enum AuditCategory: string
{
    case ACCESS = 'access';

    case CLINICS = 'clinics';

    case USERS = 'users';

    case SUPPORT = 'support';

    case FEATURES = 'features';

    case REGISTRY = 'registry';
}
