<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Scheduler;

use App\Tests\Support\ScheduleProviders;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Every schedule of the application (collected by their container tag, so a
 * new one is guarded without being named here) is consumed by the scheduler
 * worker. A worker that stops on a multiple of a task period always restarts
 * on that task's tick and loses it every time: the compose time limit must
 * never be a multiple of any task period.
 */
final class SchedulerWorkerTimeLimitTest extends KernelTestCase
{
    public function testTheSchedulerWorkerConsumesEverySchedule(): void
    {
        $command = $this->command();

        foreach (array_keys($this->providers()) as $name) {
            self::assertMatchesRegularExpression(
                \sprintf('/messenger:consume [^\n]*\bscheduler_%s\b/', preg_quote($name, '/')),
                $command,
                \sprintf('The scheduler worker does not consume the "%s" schedule.', $name),
            );
        }
    }

    public function testWorkerTimeLimitIsNotAMultipleOfAnyTaskPeriod(): void
    {
        self::assertSame(1, preg_match('/--time-limit=(\d+)/', $this->command(), $match));
        $timeLimit = (int) $match[1];

        $periods = [];

        foreach ($this->providers() as $name => $provider) {
            foreach ($provider->getSchedule()->getRecurringMessages() as $message) {
                $first = $message->getTrigger()->getNextRunDate(new \DateTimeImmutable('2026-10-17 10:00:00'));
                self::assertNotNull($first);
                $second = $message->getTrigger()->getNextRunDate($first);
                self::assertNotNull($second);
                $periods[$name . ':' . $message->getId()] = $second->getTimestamp() - $first->getTimestamp();
            }
        }

        self::assertNotEmpty($periods);

        foreach ($periods as $task => $period) {
            self::assertGreaterThan(0, $period);
            self::assertNotSame(0, $timeLimit % $period, \sprintf('The scheduler worker stops every %d s, a multiple of the %d s period of %s.', $timeLimit, $period, $task));
        }
    }

    private function command(): string
    {
        $compose = file_get_contents(\dirname(__DIR__, 5) . '/compose.yaml');
        self::assertIsString($compose);
        self::assertSame(1, preg_match('/^.*messenger:consume scheduler_default\b.*$/m', $compose, $match));
        \assert(isset($match[0]));

        return $match[0];
    }

    /**
     * @return array<string, ScheduleProviderInterface>
     */
    private function providers(): array
    {
        $providers = self::getContainer()->get(ScheduleProviders::class);
        \assert($providers instanceof ScheduleProviders);
        self::assertArrayHasKey('default', $providers->all());

        return $providers->all();
    }
}
