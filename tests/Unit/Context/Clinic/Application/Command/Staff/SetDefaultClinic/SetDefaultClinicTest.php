<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\SetDefaultClinic;

use App\Context\Clinic\Application\Command\Staff\SetDefaultClinic\SetDefaultClinic;
use PHPUnit\Framework\TestCase;

final class SetDefaultClinicTest extends TestCase
{
    public function testConstructStoresProperties(): void
    {
        $command = new SetDefaultClinic(
            clinicId: 'clinic-uuid',
            userId: 'user-uuid',
        );

        self::assertSame('clinic-uuid', $command->clinicId);
        self::assertSame('user-uuid', $command->userId);
    }
}
