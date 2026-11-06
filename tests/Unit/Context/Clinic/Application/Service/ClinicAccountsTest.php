<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Service;

use App\Context\Clinic\Application\Service\ClinicAccounts;
use App\Context\Clinic\Domain\Account\ClinicAccount;
use App\Context\Clinic\Domain\Account\ClinicPlan;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinicAccounts;
use App\Tests\Unit\Context\Clinic\Support\InMemoryClinics;
use PHPUnit\Framework\TestCase;

final class ClinicAccountsTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c1a1';

    public function testTheStoredAccountIsAnswered(): void
    {
        $accounts = new InMemoryClinicAccounts();
        $stored   = ClinicAccount::open(ClinicId::fromString(self::CLINIC), ClinicPlan::PAID, null, null, new \DateTimeImmutable());
        $accounts->save($stored);

        self::assertSame($stored, $this->service($accounts)->of(self::CLINIC));
    }

    public function testAClinicCreatedBeforeTheConsoleReadsTheDefaultAccount(): void
    {
        $account = $this->service(new InMemoryClinicAccounts())->of(self::CLINIC);

        self::assertSame([self::CLINIC, ClinicPlan::PILOT, '2026-09-01 08:00:00'], [$account->clinicId()->toString(), $account->plan(), $account->createdAt()->format('Y-m-d H:i:s')]);
    }

    public function testAnUnknownClinicHasNoAccount(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Clinic with ID "01960000-0000-7000-8000-00000000c1ff" not found.');

        $this->service(new InMemoryClinicAccounts())->of('01960000-0000-7000-8000-00000000c1ff');
    }

    public function testTheClinicIsAnswered(): void
    {
        self::assertSame(self::CLINIC, $this->service(new InMemoryClinicAccounts())->clinic(self::CLINIC)->id()->toString());
    }

    private function service(InMemoryClinicAccounts $accounts): ClinicAccounts
    {
        return new ClinicAccounts(new InMemoryClinics()->add(InMemoryClinics::clinic(self::CLINIC)), $accounts);
    }
}
