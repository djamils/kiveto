<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulationScenario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SimulationScenarioTest extends TestCase
{
    #[DataProvider('provideScenarioIsChosenByTheBuyerSirenCases')]
    public function testScenarioIsChosenByTheBuyerSiren(?string $siren, SimulationScenario $expected): void
    {
        self::assertSame($expected, SimulationScenario::forBuyerSiren($siren));
    }

    /**
     * @return iterable<string, array{?string, SimulationScenario}>
     */
    public static function provideScenarioIsChosenByTheBuyerSirenCases(): iterable
    {
        yield 'rejected' => ['000002139', SimulationScenario::REJECTED];
        yield 'refused' => ['000002105', SimulationScenario::REFUSED];
        yield 'disputed' => ['000002071', SimulationScenario::DISPUTED];
        yield 'inadmissible' => ['000005017', SimulationScenario::INADMISSIBLE];
        yield 'not transmitted' => ['000000000', SimulationScenario::NOT_TRANSMITTED];
        yield 'any other buyer' => ['523456788', SimulationScenario::NOMINAL];
        yield 'no buyer' => [null, SimulationScenario::NOMINAL];
    }

    public function testEveryScenarioStartsAtTheDepositOrIsInadmissible(): void
    {
        foreach (SimulationScenario::cases() as $scenario) {
            $first = $scenario->steps()[0][0]->value;

            self::assertContains($first, [200, 501], $scenario->value);
        }
    }
}
