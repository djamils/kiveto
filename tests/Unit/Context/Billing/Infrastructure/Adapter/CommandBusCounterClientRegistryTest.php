<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Adapter;

use App\Context\Billing\Application\Command\RecordCounterSale\CounterSaleNewClient;
use App\Context\Billing\Infrastructure\Adapter\Client\CommandBusCounterClientRegistry;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\Client\Application\Command\UpdateClientIdentity\UpdateClientIdentity;
use App\Shared\Application\Bus\CommandBusInterface;
use PHPUnit\Framework\TestCase;

/**
 * The newcomer's record, opened in the Client context.
 */
final class CommandBusCounterClientRegistryTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-00000000c801';
    private const string CLIENT_ID = '01960000-0000-7000-8000-00000000c802';

    public function testTheRecordCarriesTheMobileTheEmailAndTheCivility(): void
    {
        $sent = [];
        $bus  = $this->bus($sent);

        $id = new CommandBusCounterClientRegistry($bus)->register(self::CLINIC_ID, new CounterSaleNewClient('madam', 'Benali', 'Nadia', '0618427731', 'nadia@example.fr'));

        self::assertSame(self::CLIENT_ID, $id);
        self::assertCount(2, $sent);
        self::assertInstanceOf(CreateClient::class, $sent[0]);
        self::assertSame(self::CLINIC_ID, $sent[0]->clinicId);
        self::assertSame('Nadia', $sent[0]->firstName);
        self::assertSame('Benali', $sent[0]->lastName);
        self::assertSame(['phone', 'email'], array_map(static fn ($c): string => $c->type, $sent[0]->contactMethods));
        self::assertSame(['+33618427731', 'nadia@example.fr'], array_map(static fn ($c): string => $c->value, $sent[0]->contactMethods));
        self::assertSame([true, true], array_map(static fn ($c): bool => $c->isPrimary, $sent[0]->contactMethods));
        self::assertInstanceOf(UpdateClientIdentity::class, $sent[1]);
        self::assertSame(self::CLIENT_ID, $sent[1]->clientId);
        self::assertSame('madam', $sent[1]->civility);
    }

    public function testAnEmailAloneIsEnough(): void
    {
        $sent = [];

        new CommandBusCounterClientRegistry($this->bus($sent))->register(self::CLINIC_ID, new CounterSaleNewClient('mister', 'Petit', 'Lucas', email: 'lucas@example.fr'));

        self::assertInstanceOf(CreateClient::class, $sent[0]);
        self::assertSame(['email'], array_map(static fn ($c): string => $c->type, $sent[0]->contactMethods));
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
