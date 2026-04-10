<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Staff\GetUserMembershipInClinic;

use App\Context\Clinic\Application\Query\Staff\GetUserMembershipInClinic\GetUserMembershipInClinic;
use PHPUnit\Framework\TestCase;

final class GetUserMembershipInClinicTest extends TestCase
{
    public function testConstruct(): void
    {
        $query = new GetUserMembershipInClinic('user-uuid', 'clinic-uuid');

        self::assertSame('user-uuid', $query->userId);
        self::assertSame('clinic-uuid', $query->clinicId);
    }
}
