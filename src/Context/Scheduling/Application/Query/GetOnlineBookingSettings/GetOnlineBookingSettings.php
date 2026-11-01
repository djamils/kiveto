<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetOnlineBookingSettings;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The clinic's online booking settings, or the defaults when it never
 * saved any → OnlineBookingSettingsView.
 */
final readonly class GetOnlineBookingSettings implements QueryInterface
{
    public function __construct(public string $clinicId)
    {
    }
}
