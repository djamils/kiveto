<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Api\Portal\Support;

use App\Context\ClientPortal\Application\Query\HasConfirmedClientLink\HasConfirmedClientLink;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Support\PortalClinicGuard;
use App\Presentation\Api\Shared\Problem\ApiProblem;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class PortalClinicGuardTest extends TestCase
{
    public function testAConfirmedLinkOpensTheClinic(): void
    {
        $bus = new AnsweringQueryBus([HasConfirmedClientLink::class => true]);

        new PortalClinicGuard($bus)->require('user-1', 'clinic-1');

        self::assertCount(1, $bus->asked);
        $query = $bus->asked[0];
        self::assertInstanceOf(HasConfirmedClientLink::class, $query);
        self::assertSame(['user-1', 'clinic-1'], [$query->userId, $query->clinicId]);
    }

    #[TestWith([false])]
    #[TestWith([null])]
    #[TestWith([1])]
    public function testWithoutAConfirmedLinkTheClinicIsNotFound(mixed $answer): void
    {
        try {
            $bus = new AnsweringQueryBus([HasConfirmedClientLink::class => $answer]);
            new PortalClinicGuard($bus)->require('user-1', 'clinic-1');
            self::fail('The clinic should have been hidden.');
        } catch (ApiProblem $problem) {
            self::assertSame([PortalProblemMap::NOT_FOUND, 404], [$problem->getType(), $problem->getStatus()]);
        }
    }
}
