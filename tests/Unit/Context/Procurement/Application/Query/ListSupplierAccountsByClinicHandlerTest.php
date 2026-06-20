<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Query;

use App\Context\Procurement\Application\Port\SupplierAccountReadRepositoryInterface;
use App\Context\Procurement\Application\Query\ListSupplierAccountsByClinic\ListSupplierAccountsByClinic;
use App\Context\Procurement\Application\Query\ListSupplierAccountsByClinic\ListSupplierAccountsByClinicHandler;
use PHPUnit\Framework\TestCase;

final class ListSupplierAccountsByClinicHandlerTest extends TestCase
{
    private const string CLINIC_UUID = '01932b00-0000-7000-8000-000000000010';

    public function testItReturnsAccountsForClinic(): void
    {
        $readRepository = $this->createStub(SupplierAccountReadRepositoryInterface::class);
        $readRepository->method('findByClinic')->with(self::CLINIC_UUID)
            ->willReturn([['id' => 'aaa', 'customerCode' => 'CLI-001']])
        ;

        $handler = new ListSupplierAccountsByClinicHandler($readRepository);
        $result  = $handler(new ListSupplierAccountsByClinic(self::CLINIC_UUID));

        self::assertCount(1, $result);
        self::assertSame('CLI-001', $result[0]['customerCode']);
    }
}
