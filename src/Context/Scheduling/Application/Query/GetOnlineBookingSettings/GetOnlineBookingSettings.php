<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetOnlineBookingSettings;

use App\Shared\Application\Bus\RequestCacheableQueryInterface;

/**
 * The clinic's online booking settings, or the defaults when it never
 * saved any → OnlineBookingSettingsView. Settings: once for the request.
 */
final readonly class GetOnlineBookingSettings implements RequestCacheableQueryInterface
{
    public function __construct(public string $clinicId)
    {
    }
}
