<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Twig;

use App\Presentation\Backoffice\ViewModel\ConsoleFormat;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * The console's shell (`console_shell()`) and its French formats, dates
 * in the admin's display time zone.
 */
final class ConsoleExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('console_shell', [ConsoleRuntime::class, 'shell']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('co_when', [ConsoleRuntime::class, 'when']),
            new TwigFilter('co_date', [ConsoleRuntime::class, 'shortDate']),
            new TwigFilter('co_long_date', [ConsoleRuntime::class, 'longDate']),
            new TwigFilter('co_day_date', [ConsoleRuntime::class, 'dayDate']),
            new TwigFilter('co_day_time', [ConsoleRuntime::class, 'shortDayTime']),
            new TwigFilter('co_log_date', [ConsoleRuntime::class, 'logDate']),
            new TwigFilter('co_time', [ConsoleRuntime::class, 'time']),
            new TwigFilter('co_initials', ConsoleFormat::initials(...)),
            new TwigFilter('co_ip', ConsoleFormat::maskedIp(...)),
            new TwigFilter('co_duration', ConsoleFormat::duration(...)),
            new TwigFilter('co_euros', ConsoleFormat::euros(...)),
        ];
    }
}
