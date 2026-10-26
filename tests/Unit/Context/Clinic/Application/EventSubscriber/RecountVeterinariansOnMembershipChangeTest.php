<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\EventSubscriber;

use App\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts\RecordVeterinarianDailyCounts;
use App\Context\Clinic\Application\EventSubscriber\RecountVeterinariansOnMembershipChange;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipCreated;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipDisabled;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipEnabled;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipRoleChanged;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipValidityChanged;
use App\Shared\Application\Bus\CommandBusInterface;
use PHPUnit\Framework\TestCase;

final class RecountVeterinariansOnMembershipChangeTest extends TestCase
{
    public function testEveryMembershipChangeRecountsItsClinic(): void
    {
        $dispatched = [];
        $bus        = $this->createStub(CommandBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $command) use (&$dispatched): null {
            $dispatched[] = $command;

            return null;
        });

        $subscriber = new RecountVeterinariansOnMembershipChange($bus);
        $subscriber->onCreated(new ClinicMembershipCreated('m', 'c1', 'u', 'VETERINARY', 'EMPLOYEE', '2026-01-01', null));
        $subscriber->onEnabled(self::event(ClinicMembershipEnabled::class, 'c2'));
        $subscriber->onDisabled(self::event(ClinicMembershipDisabled::class, 'c3'));
        $subscriber->onRoleChanged(self::event(ClinicMembershipRoleChanged::class, 'c4'));
        $subscriber->onValidityChanged(new ClinicMembershipValidityChanged('m', 'c5', 'u', '2026-01-01', null));

        self::assertSame(['c1', 'c2', 'c3', 'c4', 'c5'], array_map(static function (object $command): ?string {
            self::assertInstanceOf(RecordVeterinarianDailyCounts::class, $command);

            return $command->clinicId;
        }, $dispatched));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function event(string $class, string $clinicId): object
    {
        $reflection = new \ReflectionClass($class);
        $arguments  = [];

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $arguments[$parameter->getName()] = match ($parameter->getName()) {
                'clinicId' => $clinicId,
                default    => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : 'x',
            };
        }

        return $reflection->newInstanceArgs($arguments);
    }
}
