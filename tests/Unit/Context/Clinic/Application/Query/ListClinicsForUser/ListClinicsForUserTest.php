<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\ListClinicsForUser;

use App\Context\Clinic\Application\Query\ListClinicsForUser\ListClinicsForUser;
use PHPUnit\Framework\TestCase;

final class ListClinicsForUserTest extends TestCase
{
    public function testConstruct(): void
    {
        $query = new ListClinicsForUser('user-uuid');

        self::assertSame('user-uuid', $query->userId);
    }
}
