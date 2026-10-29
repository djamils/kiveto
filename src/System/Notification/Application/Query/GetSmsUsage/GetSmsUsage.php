<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Query\GetSmsUsage;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The clinic's SMS consumption for the current quota month (Europe/Paris).
 */
final readonly class GetSmsUsage implements QueryInterface
{
    public function __construct(public string $clinicId)
    {
    }
}
