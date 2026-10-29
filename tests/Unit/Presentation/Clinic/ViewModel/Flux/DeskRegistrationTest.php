<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\ViewModel\Flux;

use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Presentation\Clinic\ViewModel\Flux\DeskRegistration;
use App\Shared\Application\Bus\CommandBusInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The phone given at the desk is labelled by its prefix, and a mobile gets
 * the SMS appointment reminders on by default (D9).
 */
final class DeskRegistrationTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-00000000c801';
    private const string CLIENT_ID = '01960000-0000-7000-8000-00000000c802';

    /**
     * @return iterable<string, array{string, string, string, array<string, list<string>>}>
     */
    public static function provideThePhoneIsLabelledByItsPrefixCases(): iterable
    {
        yield 'a mobile' => ['06 12 34 56 78', '+33612345678', 'mobile', ['appointment_reminders' => ['sms']]];
        yield 'an overseas mobile' => ['+262 692 12 34 56', '+262692123456', 'mobile', ['appointment_reminders' => ['sms']]];
        yield 'a landline' => ['01 42 64 18 00', '+33142641800', 'home', []];
        yield 'a 09 number' => ['09 70 12 34 56', '+33970123456', 'home', []];
    }

    /**
     * @param array<string, list<string>> $consents
     */
    #[DataProvider('provideThePhoneIsLabelledByItsPrefixCases')]
    public function testThePhoneIsLabelledByItsPrefix(string $typed, string $stored, string $label, array $consents): void
    {
        $sent = [];
        $bus  = $this->createStub(CommandBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $command) use (&$sent): ?string {
            $sent[] = $command;

            return $command instanceof CreateClient ? self::CLIENT_ID : null;
        });

        self::assertSame(self::CLIENT_ID, new DeskRegistration($bus)->createOwner(self::CLINIC_ID, 'Paul', 'Garcia', $typed));

        self::assertCount(1, $sent);
        self::assertInstanceOf(CreateClient::class, $sent[0]);
        self::assertCount(1, $sent[0]->contactMethods);
        self::assertSame($stored, $sent[0]->contactMethods[0]->value);
        self::assertSame($label, $sent[0]->contactMethods[0]->label);
        self::assertSame($consents, $sent[0]->channelsByConsentKind);
    }
}
