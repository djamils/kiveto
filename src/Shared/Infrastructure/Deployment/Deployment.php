<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Deployment;

/**
 * Which deployment this is (KIVETO_DEPLOYMENT), independently of APP_ENV:
 * STAGING and DEMO run with APP_ENV=prod, like production, but on synthetic
 * data. The guards that protect real people and real money key on this
 * value, never on `kernel.environment`. Unset, it is production (`.env`),
 * the strictest; an unknown value fails when first read.
 */
enum Deployment: string
{
    /** Workstations and the test suite. */
    case DEV = 'dev';

    case STAGING = 'staging';

    case DEMO = 'demo';

    case PRODUCTION = 'production';
}
