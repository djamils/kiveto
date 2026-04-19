<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians;

use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use PHPUnit\Framework\TestCase;

final class ListClinicVeterinariansTest extends TestCase
{
    public function testConstructsWithClinicId(): void
    {
        $query = new ListClinicVeterinarians('12345678-9abc-def0-1234-56789abcdef0');

        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $query->clinicId);
    }

    public function testClinicVeterinarianItemExposesFields(): void
    {
        $item = new ClinicVeterinarianItem(
            userId: '01912345-6789-7abc-8def-0000000000a1',
            role: 'VETERINARY',
            engagement: 'EMPLOYEE',
            membershipId: 'aaaaaaaa-bbbb-7ccc-8ddd-eeeeeeeeee01',
        );

        self::assertSame('01912345-6789-7abc-8def-0000000000a1', $item->userId);
        self::assertSame('VETERINARY', $item->role);
        self::assertSame('EMPLOYEE', $item->engagement);
        self::assertSame('aaaaaaaa-bbbb-7ccc-8ddd-eeeeeeeeee01', $item->membershipId);
        self::assertNull($item->displayName);
    }
}
