<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\FeatureCatalog\Infrastructure\Adapter\Clinic;

use App\Context\Clinic\Application\Query\Account\GetClinicAccount\ClinicAccountView;
use App\Context\Clinic\Application\Query\Account\GetClinicAccount\GetClinicAccount;
use App\Context\Clinic\Application\Query\Console\ConsoleClinicRow;
use App\Context\Clinic\Application\Query\Console\ListConsoleClinics;
use App\System\FeatureCatalog\Infrastructure\Adapter\Clinic\QueryBusClinicPlans;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

final class QueryBusClinicPlansTest extends TestCase
{
    private const string A = '01960000-0000-7000-8000-00000000fa01';
    private const string B = '01960000-0000-7000-8000-00000000fa02';
    private const string C = '01960000-0000-7000-8000-00000000fa03';

    public function testOneClinicsPlanComesFromItsAccount(): void
    {
        $bus = new AnsweringQueryBus()->answer(GetClinicAccount::class, new ClinicAccountView(self::A, 'paid', 'Payant', null, null, null, '', null, null, null, null, null, null));

        self::assertSame([self::A => 'paid'], new QueryBusClinicPlans($bus)->plansOf([self::A]));
        self::assertInstanceOf(GetClinicAccount::class, $bus->asked[0] ?? null);
    }

    public function testNoAccountAnswerNoPlan(): void
    {
        self::assertSame([], new QueryBusClinicPlans(new AnsweringQueryBus()->answer(GetClinicAccount::class, null))->plansOf([self::A]));
    }

    public function testSeveralClinicsComeFromTheConsoleList(): void
    {
        $bus = new AnsweringQueryBus()->answer(ListConsoleClinics::class, [self::row(self::A, 'paid'), self::row(self::B, 'pilot'), self::row(self::C, 'paid'), 'not a row']);

        self::assertSame([self::A => 'paid', self::B => 'pilot'], new QueryBusClinicPlans($bus)->plansOf([self::A, self::B]), 'only the clinics asked about');
        self::assertSame([], new QueryBusClinicPlans(new AnsweringQueryBus()->answer(ListConsoleClinics::class, null))->plansOf([self::A, self::B]));
    }

    private static function row(string $id, string $plan): ConsoleClinicRow
    {
        return new ConsoleClinicRow($id, 'Clinique', 'clinique', null, null, null, null, 'active', $plan, null, null, null, new \DateTimeImmutable(), 1, 2);
    }
}
