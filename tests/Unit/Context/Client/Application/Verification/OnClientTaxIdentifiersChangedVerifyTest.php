<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Verification;

use App\Context\Client\Application\Command\VerifyClientTaxProfile\VerifyClientTaxProfile;
use App\Context\Client\Application\Verification\OnClientTaxIdentifiersChangedVerify;
use App\Context\Client\Domain\Event\ClientTaxIdentifiersChanged;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OnClientTaxIdentifiersChangedVerify::class)]
final class OnClientTaxIdentifiersChangedVerifyTest extends TestCase
{
    public function testNewIdentifiersOfABusinessAreVerified(): void
    {
        $bus = $this->createMock(ExternalApiCommandBusInterface::class);
        $bus->expects(self::once())->method('send')->with(self::callback(
            static fn (VerifyClientTaxProfile $command): bool => 'clinic-1' === $command->clinicId && 'client-1' === $command->clientId,
        ));

        new OnClientTaxIdentifiersChangedVerify($bus)(new ClientTaxIdentifiersChanged('client-1', 'clinic-1', 'french_business', null, '523456788', null, null));
    }

    public function testAClientThatBecameAnIndividualIsNotVerified(): void
    {
        $bus = $this->createMock(ExternalApiCommandBusInterface::class);
        $bus->expects(self::never())->method('send');

        new OnClientTaxIdentifiersChangedVerify($bus)(new ClientTaxIdentifiersChanged('client-1', 'clinic-1', 'individual', '523456788', null, null, null));
    }
}
