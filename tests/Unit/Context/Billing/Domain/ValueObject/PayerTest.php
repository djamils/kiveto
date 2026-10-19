<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain\ValueObject;

use App\Context\Billing\Domain\Exception\InvalidPayerException;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PayerId;
use PHPUnit\Framework\TestCase;

final class PayerTest extends TestCase
{
    private const string CLIENT_ID = '01960000-0000-7000-8000-0000000000f1';

    public function testItKeepsTheClientAndItsAddress(): void
    {
        $payer = Payer::create(
            PayerId::fromString(self::CLIENT_ID),
            '  Sophie Dupont  ',
            ['12 rue des Lilas', '  ', '75011 Paris'],
        );

        self::assertSame('Sophie Dupont', $payer->displayName());
        self::assertSame(self::CLIENT_ID, $payer->clientId()?->toString());
        // Blank lines are dropped rather than printed as gaps on the invoice.
        self::assertSame(['12 rue des Lilas', '75011 Paris'], $payer->addressLines());
    }

    public function testAnAnonymousPayerHasNoClient(): void
    {
        $payer = Payer::anonymous();

        self::assertNull($payer->clientId());
        self::assertSame('Client de passage', $payer->displayName());
        self::assertSame([], $payer->addressLines());
    }

    public function testACounterSaleWithoutContactDetailsIsMadeOutToNobody(): void
    {
        $payer = Payer::contactDetailsRefused();

        self::assertNull($payer->clientId());
        self::assertSame('Vente sans coordonnées', $payer->displayName());
        self::assertSame([], $payer->addressLines());
    }

    public function testAnEmptyNameIsRejected(): void
    {
        $this->expectException(InvalidPayerException::class);
        $this->expectExceptionMessage('The payer name cannot be empty.');

        Payer::create(null, '   ');
    }

    public function testTwoPayersAreEqualOnNameAddressAndClient(): void
    {
        $one = Payer::create(PayerId::fromString(self::CLIENT_ID), 'Sophie Dupont', ['12 rue des Lilas']);
        $two = Payer::create(PayerId::fromString(self::CLIENT_ID), 'Sophie Dupont', ['12 rue des Lilas']);

        self::assertTrue($one->equals($two));
        self::assertFalse($one->equals(Payer::create(null, 'Sophie Dupont', ['12 rue des Lilas'])));
        self::assertFalse($one->equals(Payer::create(PayerId::fromString(self::CLIENT_ID), 'Marc Lefebvre')));
    }

    public function testAStructureIsBilledToTheAttentionOfItsContact(): void
    {
        $payer = Payer::create(null, 'Élevage des Calanques', [], '  Mme Claire Rostand ');

        self::assertSame('Mme Claire Rostand', $payer->attention());
        self::assertNull(Payer::create(null, 'Sophie Dupont', [], '  ')->attention());
        self::assertNull(Payer::create(null, 'Sophie Dupont')->attention());
        self::assertFalse($payer->equals(Payer::create(null, 'Élevage des Calanques')));
        self::assertTrue($payer->equals(Payer::create(null, 'Élevage des Calanques', [], 'Mme Claire Rostand')));
    }
}
