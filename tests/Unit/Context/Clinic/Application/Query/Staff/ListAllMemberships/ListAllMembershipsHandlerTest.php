<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Staff\ListAllMemberships;

use App\Context\Clinic\Application\Port\MembershipAdminRepositoryInterface;
use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\ListAllMemberships;
use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\ListAllMembershipsHandler;
use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\MembershipCollection;
use PHPUnit\Framework\TestCase;

final class ListAllMembershipsHandlerTest extends TestCase
{
    public function testDelegatesToRepository(): void
    {
        $collection = new MembershipCollection(memberships: [], total: 0);

        $repo = $this->createStub(MembershipAdminRepositoryInterface::class);
        $repo->method('listAll')->willReturn($collection);

        $handler = new ListAllMembershipsHandler($repo);
        $result  = $handler(new ListAllMemberships());

        self::assertSame(0, $result->total);
        self::assertSame([], $result->memberships);
    }
}
