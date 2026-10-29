<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Infrastructure\Rendering;

use App\System\Notification\Application\Exception\InvalidNotificationRequestException;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\MessagePurpose;
use App\System\Notification\Infrastructure\Rendering\TemplateCatalog;
use PHPUnit\Framework\TestCase;

final class TemplateCatalogTest extends TestCase
{
    public function testEveryPurposeHasAnEmailOrAnSmsTemplate(): void
    {
        foreach (MessagePurpose::cases() as $purpose) {
            self::assertTrue(TemplateCatalog::supports($purpose, Channel::EMAIL) || TemplateCatalog::supports($purpose, Channel::SMS), $purpose->value);
        }

        self::assertFalse(TemplateCatalog::supports(MessagePurpose::INVOICE_SENT, Channel::SMS));
        self::assertTrue(TemplateCatalog::supports(MessagePurpose::APPOINTMENT_REMINDER, Channel::EMAIL), 'the cap fallback');
        self::assertSame('appointment_reminder', TemplateCatalog::templateName(MessagePurpose::APPOINTMENT_REMINDER));
    }

    public function testTheDeclaredParametersAreAccepted(): void
    {
        $parameters = ['invoiceNumber' => 'FA-2026-0702', 'shortDate' => '04/09', 'balanceDue' => '64,50 €'];

        self::assertSame($parameters, TemplateCatalog::validate(MessagePurpose::INVOICE_REMINDER, Channel::SMS, $parameters));
    }

    public function testAMissingOrAnExtraParameterIsRefused(): void
    {
        $this->expectException(InvalidNotificationRequestException::class);
        $this->expectExceptionMessage('INVOICE_REMINDER SMS parameters: missing [balanceDue], unexpected [amount].');

        TemplateCatalog::validate(MessagePurpose::INVOICE_REMINDER, Channel::SMS, ['invoiceNumber' => 'FA', 'shortDate' => '04/09', 'amount' => '1']);
    }

    public function testAnUnknownChannelIsRefused(): void
    {
        $this->expectException(InvalidNotificationRequestException::class);
        $this->expectExceptionMessage('INVOICE_SENT has no SMS template.');

        TemplateCatalog::validate(MessagePurpose::INVOICE_SENT, Channel::SMS, []);
    }

    public function testAListWhereAScalarIsExpectedIsRefused(): void
    {
        $this->expectException(InvalidNotificationRequestException::class);
        $this->expectExceptionMessage('parameter "sentAtLabel" must be a scalar');

        TemplateCatalog::validate(MessagePurpose::TEST, Channel::SMS, ['sentAtLabel' => ['a']]);
    }

    public function testANestedListIsRefused(): void
    {
        $this->expectException(InvalidNotificationRequestException::class);
        $this->expectExceptionMessage('parameter "documentLabels" must be a list of scalars');

        TemplateCatalog::validate(MessagePurpose::CONSULTATION_DOCUMENTS, Channel::EMAIL, [
            'addressee'             => 'Monsieur Lefebvre',
            'animalName'            => 'Minou',
            'consultationDateLabel' => '16 octobre 2026',
            'consultationLabel'     => '16/10/2026 · Dr Lambert',
            'documentLabels'        => [['Compte rendu']],
            'documentValues'        => ['Joint'],
        ]);
    }
}
