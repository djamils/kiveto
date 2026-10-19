<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Test-only: every schedule provider of the container, by schedule name.
 */
final readonly class ScheduleProviders
{
    /**
     * @param ServiceLocator<ScheduleProviderInterface> $providers
     */
    public function __construct(private ServiceLocator $providers)
    {
    }

    /**
     * @return array<string, ScheduleProviderInterface>
     */
    public function all(): array
    {
        $all = [];

        foreach (array_keys($this->providers->getProvidedServices()) as $name) {
            $all[$name] = $this->providers->get($name);
        }

        return $all;
    }
}
