<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Account;

use App\Context\Clinic\Domain\Account\ParallelSoftware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParallelSoftwareTest extends TestCase
{
    #[DataProvider('provideEachSoftwareIsNamedAsItsPublisherWritesItCases')]
    public function testEachSoftwareIsNamedAsItsPublisherWritesIt(ParallelSoftware $software, string $label): void
    {
        self::assertSame($label, $software->label());
    }

    /**
     * @return iterable<string, array{ParallelSoftware, string}>
     */
    public static function provideEachSoftwareIsNamedAsItsPublisherWritesItCases(): iterable
    {
        yield 'vetup' => [ParallelSoftware::VETUP, 'Vetup'];

        yield 'dr.veto' => [ParallelSoftware::DR_VETO, 'dr.veto'];

        yield 'vetocom' => [ParallelSoftware::VETOCOM, 'Vétocom'];

        yield 'epivet' => [ParallelSoftware::EPIVET, 'Epivet'];

        yield 'other' => [ParallelSoftware::OTHER, 'Autre'];

        yield 'none' => [ParallelSoftware::NONE, 'Aucun'];
    }
}
