<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Adapter\Client;

use App\Context\Client\Application\Query\IsPortalMobileNumber\IsPortalMobileNumber;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Infrastructure\Adapter\Client\QueryBusMobilePhonePolicy;
use PHPUnit\Framework\TestCase;

final class QueryBusMobilePhonePolicyTest extends TestCase
{
    public function testThePhoneIsAskedToClientAndOnlyTrueIsAccepted(): void
    {
        $bus = new class implements QueryBusInterface {
            /** @var list<string> */
            public array $asked = [];

            public function ask(object $query, object ...$stamps): mixed
            {
                \assert($query instanceof IsPortalMobileNumber);
                $this->asked[] = $query->phone;

                return match ($query->phone) {
                    '+33612345678' => true,
                    '+33145678901' => false,
                    default        => 'yes',
                };
            }
        };
        $policy = new QueryBusMobilePhonePolicy($bus);

        self::assertSame([true, false, false], [$policy->isPortalMobile('+33612345678'), $policy->isPortalMobile('+33145678901'), $policy->isPortalMobile('+3225551234')]);
        self::assertSame(['+33612345678', '+33145678901', '+3225551234'], $bus->asked);
    }
}
