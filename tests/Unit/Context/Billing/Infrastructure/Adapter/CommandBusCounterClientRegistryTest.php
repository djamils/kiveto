<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Adapter;

use App\Context\Billing\Application\Command\RecordCounterSale\ClientTaxProfileInput;
use App\Context\Billing\Application\Command\RecordCounterSale\CounterSaleNewClient;
use App\Context\Billing\Infrastructure\Adapter\Client\CommandBusCounterClientRegistry;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Shared\Application\Bus\CommandBusInterface;
use PHPUnit\Framework\TestCase;

/**
 * The newcomer's record, opened in the Client context.
 */
final class CommandBusCounterClientRegistryTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-00000000c801';
    private const string CLIENT_ID = '01960000-0000-7000-8000-00000000c802';

    public function testASingleCommandCarriesTheMobileTheEmailAndTheCivility(): void
    {
        $sent = [];
        $bus  = $this->bus($sent);

        $id = new CommandBusCounterClientRegistry($bus)->register(self::CLINIC_ID, new CounterSaleNewClient('madam', 'Benali', 'Nadia', '0618427731', 'nadia@example.fr'));

        self::assertSame(self::CLIENT_ID, $id);
        self::assertCount(1, $sent);
        self::assertInstanceOf(CreateClient::class, $sent[0]);
        self::assertSame(self::CLINIC_ID, $sent[0]->clinicId);
        self::assertSame('Nadia', $sent[0]->firstName);
        self::assertSame('Benali', $sent[0]->lastName);
        self::assertSame(['phone', 'email'], array_map(static fn ($c): string => $c->type, $sent[0]->contactMethods));
        self::assertSame(['+33618427731', 'nadia@example.fr'], array_map(static fn ($c): string => $c->value, $sent[0]->contactMethods));
        self::assertSame([true, true], array_map(static fn ($c): bool => $c->isPrimary, $sent[0]->contactMethods));
        self::assertSame('madam', $sent[0]->civility);
        self::assertSame(['mobile', 'home'], array_map(static fn ($c): string => $c->label, $sent[0]->contactMethods));
        self::assertSame(['appointment_reminders' => ['sms']], $sent[0]->channelsByConsentKind, 'SMS reminders on by default with a mobile (D9)');
    }

    public function testAPersonsLandlineIsNoMobileAndGetsNoSmsReminder(): void
    {
        $sent = [];

        new CommandBusCounterClientRegistry($this->bus($sent))->register(self::CLINIC_ID, new CounterSaleNewClient('mister', 'Petit', 'Lucas', '0142641800'));

        self::assertInstanceOf(CreateClient::class, $sent[0]);
        self::assertSame(['home'], array_map(static fn ($c): string => $c->label, $sent[0]->contactMethods));
        self::assertSame([], $sent[0]->channelsByConsentKind);
    }

    public function testAnEmailAloneIsEnough(): void
    {
        $sent = [];

        new CommandBusCounterClientRegistry($this->bus($sent))->register(self::CLINIC_ID, new CounterSaleNewClient('mister', 'Petit', 'Lucas', email: 'lucas@example.fr'));

        self::assertInstanceOf(CreateClient::class, $sent[0]);
        self::assertSame(['email'], array_map(static fn ($c): string => $c->type, $sent[0]->contactMethods));
    }

    public function testAProfessionalTravelsWithItsTaxProfileAndWorkContacts(): void
    {
        $sent = [];

        new CommandBusCounterClientRegistry($this->bus($sent))->register(self::CLINIC_ID, new CounterSaleNewClient(
            CounterSaleNewClient::MADAM,
            'Rostand',
            'Claire',
            '0491000000',
            'contact@calanques.fr',
            new ClientTaxProfileInput('french_business', 'Élevage des Calanques', '523456788', 'FR31523456788'),
            ' Chemin des Goudes ',
            '13008',
            'Marseille',
        ));

        self::assertCount(1, $sent);
        self::assertInstanceOf(CreateClient::class, $sent[0]);
        self::assertSame(['work', 'work'], array_map(static fn ($c): string => $c->label, $sent[0]->contactMethods));
        self::assertSame([], $sent[0]->channelsByConsentKind);
        self::assertNotNull($sent[0]->taxProfile);
        self::assertSame('french_business', $sent[0]->taxProfile->status);
        self::assertSame('Élevage des Calanques', $sent[0]->taxProfile->legalName);
        self::assertSame('523456788', $sent[0]->taxProfile->siren);
        self::assertSame('FR31523456788', $sent[0]->taxProfile->vatNumber);
        self::assertNotNull($sent[0]->postalAddress);
        self::assertSame('Chemin des Goudes', $sent[0]->postalAddress->streetLine1);
        self::assertSame('13008', $sent[0]->postalAddress->postalCode);
        self::assertSame('Marseille', $sent[0]->postalAddress->city);
        self::assertSame('FR', $sent[0]->postalAddress->countryCode);
    }

    public function testAnAddressWithoutPostalCodeKeepsItNull(): void
    {
        $sent = [];

        new CommandBusCounterClientRegistry($this->bus($sent))->register(self::CLINIC_ID, new CounterSaleNewClient('mister', 'Petit', 'Lucas', email: 'lucas@example.fr', street: '1 rue', city: 'Lyon'));

        self::assertInstanceOf(CreateClient::class, $sent[0]);
        self::assertNull($sent[0]->postalAddress?->postalCode);
    }

    public function testNoAddressIsSentWhenNoneWasTyped(): void
    {
        $sent = [];

        new CommandBusCounterClientRegistry($this->bus($sent))->register(self::CLINIC_ID, new CounterSaleNewClient('mister', 'Petit', 'Lucas', email: 'lucas@example.fr', street: '1 rue'));

        self::assertInstanceOf(CreateClient::class, $sent[0]);
        self::assertNull($sent[0]->postalAddress);
    }

    public function testAProfessionalsMobileStaysAMobile(): void
    {
        $sent = [];

        new CommandBusCounterClientRegistry($this->bus($sent))->register(self::CLINIC_ID, new CounterSaleNewClient(CounterSaleNewClient::MISTER, 'Marchal', 'Hugo', '0611223344', taxProfile: new ClientTaxProfileInput('french_business', 'Centre équestre du Lac', '812345676')));

        self::assertInstanceOf(CreateClient::class, $sent[0]);
        self::assertSame(['mobile'], array_map(static fn ($c): string => $c->label, $sent[0]->contactMethods));
    }

    /**
     * @param list<object> $sent
     */
    private function bus(array &$sent): CommandBusInterface
    {
        $bus = $this->createStub(CommandBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $command) use (&$sent): mixed {
            $sent[] = $command;

            return $command instanceof CreateClient ? self::CLIENT_ID : null;
        });

        return $bus;
    }
}
