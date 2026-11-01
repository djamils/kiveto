<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Infrastructure\Rendering;

use App\System\Notification\Application\Exception\InvalidNotificationRequestException;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\Criticality;
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

    public function testThePortalCodeGoesOnBothChannelsAndThePortalInvitationToo(): void
    {
        self::assertSame([true, true], [TemplateCatalog::supports(MessagePurpose::PORTAL_OTP, Channel::SMS), TemplateCatalog::supports(MessagePurpose::PORTAL_OTP, Channel::EMAIL)]);
        self::assertSame(['code' => 'scalar', 'validityMinutes' => 'scalar', 'purpose' => 'scalar'], TemplateCatalog::parameters(MessagePurpose::PORTAL_OTP, Channel::SMS), 'the wording depends on the purpose');
        self::assertSame(['code' => 'scalar', 'validityMinutes' => 'scalar'], TemplateCatalog::parameters(MessagePurpose::PORTAL_OTP, Channel::EMAIL), 'a sign-in code resent to a verified address');
        self::assertSame('portal_otp', TemplateCatalog::templateName(MessagePurpose::PORTAL_OTP));

        $invitation = ['clinicName' => 'scalar', 'ownerFirstName' => 'scalar', 'link' => 'scalar', 'expiresOn' => 'scalar'];
        self::assertSame($invitation, TemplateCatalog::parameters(MessagePurpose::PORTAL_INVITATION, Channel::SMS));
        self::assertSame($invitation, TemplateCatalog::parameters(MessagePurpose::PORTAL_INVITATION, Channel::EMAIL));
        self::assertSame('portal_invitation', TemplateCatalog::templateName(MessagePurpose::PORTAL_INVITATION));
    }

    public function testTheEmailChangeMessagesAreEmailsOnly(): void
    {
        self::assertSame([true, false], [TemplateCatalog::supports(MessagePurpose::PORTAL_EMAIL_VERIFICATION, Channel::EMAIL), TemplateCatalog::supports(MessagePurpose::PORTAL_EMAIL_VERIFICATION, Channel::SMS)]);
        self::assertSame(['code' => 'scalar', 'validityMinutes' => 'scalar'], TemplateCatalog::parameters(MessagePurpose::PORTAL_EMAIL_VERIFICATION, Channel::EMAIL));
        self::assertSame([true, false], [TemplateCatalog::supports(MessagePurpose::PORTAL_EMAIL_CHANGED_NOTICE, Channel::EMAIL), TemplateCatalog::supports(MessagePurpose::PORTAL_EMAIL_CHANGED_NOTICE, Channel::SMS)]);
        self::assertSame(['maskedNewEmail' => 'scalar', 'changedAtLabel' => 'scalar'], TemplateCatalog::parameters(MessagePurpose::PORTAL_EMAIL_CHANGED_NOTICE, Channel::EMAIL));
        self::assertSame('portal_email_changed_notice', TemplateCatalog::templateName(MessagePurpose::PORTAL_EMAIL_CHANGED_NOTICE));
    }

    public function testTheDataExportNoticeIsAnEmailWithoutLink(): void
    {
        self::assertSame([true, false], [TemplateCatalog::supports(MessagePurpose::PORTAL_DATA_EXPORT_READY, Channel::EMAIL), TemplateCatalog::supports(MessagePurpose::PORTAL_DATA_EXPORT_READY, Channel::SMS)]);
        self::assertSame(['ownerFirstName' => 'scalar', 'expiresAtLabel' => 'scalar'], TemplateCatalog::parameters(MessagePurpose::PORTAL_DATA_EXPORT_READY, Channel::EMAIL));
        self::assertSame('portal_data_export_ready', TemplateCatalog::templateName(MessagePurpose::PORTAL_DATA_EXPORT_READY));
    }

    public function testTheAnswerToAPortalRequestGoesOnBothChannels(): void
    {
        self::assertSame(['addressee' => 'scalar', 'petName' => 'scalar', 'sentence' => 'scalar', 'staffMessage' => 'scalar'], TemplateCatalog::parameters(MessagePurpose::PORTAL_REQUEST_RESOLVED, Channel::EMAIL));
        self::assertSame(['sentence' => 'scalar', 'staffMessage' => 'scalar'], TemplateCatalog::parameters(MessagePurpose::PORTAL_REQUEST_RESOLVED, Channel::SMS));
        self::assertSame('portal_request_resolved', TemplateCatalog::templateName(MessagePurpose::PORTAL_REQUEST_RESOLVED));
    }

    public function testTheDeclineOfANewClientsRequestIsAnSmsOnly(): void
    {
        self::assertSame([false, true], [TemplateCatalog::supports(MessagePurpose::PORTAL_BOOKING_DECLINED, Channel::EMAIL), TemplateCatalog::supports(MessagePurpose::PORTAL_BOOKING_DECLINED, Channel::SMS)]);
        self::assertSame(['clinicName' => 'scalar', 'slotLabel' => 'scalar'], TemplateCatalog::parameters(MessagePurpose::PORTAL_BOOKING_DECLINED, Channel::SMS));
        self::assertSame('portal_booking_declined', TemplateCatalog::templateName(MessagePurpose::PORTAL_BOOKING_DECLINED));
        self::assertSame(Criticality::NON_CRITICAL, MessagePurpose::PORTAL_BOOKING_DECLINED->criticality());
    }

    public function testTheDeclineOfAPendingRequestGoesOnBothChannels(): void
    {
        self::assertTrue(TemplateCatalog::supports(MessagePurpose::APPOINTMENT_DECLINED, Channel::EMAIL));
        self::assertTrue(TemplateCatalog::supports(MessagePurpose::APPOINTMENT_DECLINED, Channel::SMS));
        self::assertSame('appointment_declined', TemplateCatalog::templateName(MessagePurpose::APPOINTMENT_DECLINED));
        self::assertSame(['slotLabel' => 'scalar'], TemplateCatalog::parameters(MessagePurpose::APPOINTMENT_DECLINED, Channel::SMS));
        self::assertSame(
            TemplateCatalog::parameters(MessagePurpose::APPOINTMENT_CANCELLATION, Channel::EMAIL),
            TemplateCatalog::parameters(MessagePurpose::APPOINTMENT_DECLINED, Channel::EMAIL),
        );
        self::assertSame('Demande de RDV non confirmée', MessagePurpose::APPOINTMENT_DECLINED->label());
        self::assertSame(Criticality::CRITICAL, MessagePurpose::APPOINTMENT_DECLINED->criticality());
        self::assertFalse(MessagePurpose::APPOINTMENT_DECLINED->isSentByKiveto());
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
