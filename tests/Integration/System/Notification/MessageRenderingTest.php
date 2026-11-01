<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Notification;

use App\Shared\Domain\Storage\FileStorageInterface;
use App\System\Notification\Application\Port\MessageRendererInterface;
use App\System\Notification\Domain\Clinic\ClinicMessagingProfile;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\MessagePurpose;
use App\System\Notification\Domain\Message\RenderedContent;
use App\System\Notification\Domain\Message\SmsEncoding;
use App\System\Notification\Infrastructure\Rendering\Gsm7;
use App\System\Notification\Infrastructure\Rendering\TemplateCatalog;
use App\Tests\Unit\System\Notification\Support\InMemoryProfiles;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Every template rendered with the reference data of
 * docs/notifications/Notifications Kiveto.pdf.
 */
final class MessageRenderingTest extends KernelTestCase
{
    private const string LOGO_KEY = 'notification-test/clinic-logo.png';

    /** A real-length invitation link: 43-character token of OpaqueTokenHasher. */
    private const string PORTAL_LINK = 'https://portal.kiveto.app/invitation#t=Zm9vYmFyLWJhei1xdXV4LWNvcmdlLWdyYXVsdC1nYXJ';

    /**
     * The reference data of each sheet, per purpose and channel.
     *
     * @return array<string, array<string, array<string, scalar|list<scalar>>>>
     */
    public static function reference(): array
    {
        return [
            'APPOINTMENT_CONFIRMATION' => [
                'EMAIL' => ['addressee' => 'Madame Dubois', 'animalName' => 'Milo', 'subjectDateLabel' => 'jeudi 22 octobre à 9h30', 'dateLabel' => 'Jeudi 22 octobre 2026', 'timeLabel' => '9h30 · 30 min', 'reason' => 'Vaccination', 'practitionerName' => 'Dr Rousseau'],
                'SMS'   => ['animalName' => 'Milo', 'slotLabel' => 'jeudi 22/10 à 9h30', 'practitionerName' => 'Dr Rousseau'],
            ],
            'APPOINTMENT_CHANGE' => [
                'EMAIL' => ['addressee' => 'Madame Dubois', 'animalName' => 'Milo', 'subjectDateLabel' => 'vendredi 23 octobre à 11h00', 'previousSlotLabel' => 'Jeu. 22 oct. · 9h30', 'newSlotLabel' => 'Ven. 23 oct. · 11h00', 'reason' => 'Vaccination', 'practitionerName' => 'Dr Rousseau'],
                'SMS'   => ['animalName' => 'Milo', 'slotLabel' => 'vendredi 23/10 à 11h00', 'previousSlotLabel' => 'jeudi 22/10 à 9h30', 'practitionerName' => 'Dr Rousseau'],
            ],
            'APPOINTMENT_CANCELLATION' => [
                'EMAIL' => ['addressee' => 'Madame Dubois', 'animalName' => 'Milo', 'subjectDateLabel' => 'jeudi 22 octobre à 9h30', 'dateLabel' => 'Jeudi 22 octobre 2026', 'timeLabel' => '9h30', 'reason' => 'Vaccination', 'practitionerName' => 'Dr Rousseau'],
                'SMS'   => ['animalName' => 'Milo', 'slotLabel' => 'jeudi 22/10 à 9h30'],
            ],
            'APPOINTMENT_DECLINED' => [
                'EMAIL' => ['addressee' => 'Madame Dubois', 'animalName' => 'Milo', 'subjectDateLabel' => 'jeudi 22 octobre à 9h30', 'dateLabel' => 'Jeudi 22 octobre 2026', 'timeLabel' => '9h30', 'reason' => 'Vaccination', 'practitionerName' => 'Dr Rousseau'],
                'SMS'   => ['slotLabel' => 'jeudi 22/10 à 9h30'],
            ],
            'APPOINTMENT_REMINDER' => [
                'SMS'   => ['animalName' => 'Milo', 'whenLabel' => 'demain à 9h30', 'practitionerName' => 'Dr Rousseau'],
                'EMAIL' => ['addressee' => 'Madame Dubois', 'animalName' => 'Milo', 'whenLabel' => 'demain à 9h30', 'practitionerName' => 'Dr Rousseau'],
            ],
            'INVOICE_SENT' => [
                'EMAIL' => ['addressee' => 'Monsieur Lefebvre', 'invoiceNumber' => 'FA-2026-0814', 'invoiceDate' => '16/10/2026', 'purchaseLabel' => 'la consultation de Minou du 16 octobre 2026', 'vatLabels' => ['20 %'], 'vatAmounts' => ['14,78 €'], 'total' => '88,70 €', 'balanceDue' => '88,70 €'],
            ],
            'INVOICE_REMINDER' => [
                'EMAIL' => ['addressee' => 'Madame Rey', 'invoiceNumber' => 'FA-2026-0702', 'invoiceDate' => '04/09/2026', 'careLabel' => 'des soins de Cookie', 'total' => '64,50 €', 'paid' => '0,00 €', 'balanceDue' => '64,50 €'],
                'SMS'   => ['invoiceNumber' => 'FA-2026-0702', 'shortDate' => '04/09', 'balanceDue' => '64,50 €'],
            ],
            'CONSULTATION_DOCUMENTS' => [
                'EMAIL' => ['addressee' => 'Monsieur Lefebvre', 'animalName' => 'Minou', 'consultationDateLabel' => '16 octobre 2026', 'consultationLabel' => '16/10/2026 · Dr Lambert', 'documentLabels' => ['Compte rendu', 'Ordonnance', 'Facture'], 'documentValues' => ['Joint', 'ORD-2026-0391', 'FA-2026-0814 · 88,70 €']],
            ],
            'STAFF_INVITATION' => [
                'EMAIL' => ['firstName' => 'Hugo', 'inviterName' => 'Dr Claire Lambert', 'clinicName' => 'Clinique Montmartre', 'clinicCity' => 'Paris 18e', 'roleLabel' => 'Vétérinaire', 'activationUrl' => 'http://clinic.kiveto.local/invitation/abc'],
            ],
            'PASSWORD_RESET' => [
                'EMAIL' => ['accountEmail' => 'dr.rousseau@clinique-montmartre.fr', 'resetUrl' => 'http://clinic.kiveto.local/reinitialiser/abc', 'requestedAtLabel' => '16/10/2026 à 08:14'],
            ],
            'TEST' => [
                'EMAIL' => ['sentAtLabel' => '26/10/2026 à 10:42'],
                'SMS'   => ['sentAtLabel' => '26/10/2026 à 10:42'],
            ],
            'PORTAL_OTP' => [
                'SMS'   => ['code' => '123456', 'validityMinutes' => 10, 'purpose' => 'login'],
                'EMAIL' => ['code' => '123456', 'validityMinutes' => 10],
            ],
            'PORTAL_INVITATION' => [
                'EMAIL' => ['clinicName' => 'Clinique Montmartre', 'ownerFirstName' => 'Sophie', 'link' => self::PORTAL_LINK, 'expiresOn' => '05/11/2026'],
                'SMS'   => ['clinicName' => 'Clinique Montmartre', 'ownerFirstName' => 'Sophie', 'link' => self::PORTAL_LINK, 'expiresOn' => '05/11/2026'],
            ],
            'PORTAL_EMAIL_VERIFICATION' => [
                'EMAIL' => ['code' => '123456', 'validityMinutes' => 10],
            ],
            'PORTAL_EMAIL_CHANGED_NOTICE' => [
                'EMAIL' => ['maskedNewEmail' => 'n***@example.com', 'changedAtLabel' => '01/11/2026 à 14:05'],
            ],
            'PORTAL_DATA_EXPORT_READY' => [
                'EMAIL' => ['ownerFirstName' => 'Sophie', 'expiresAtLabel' => '04/11/2026 à 11:02'],
            ],
            'PORTAL_REQUEST_RESOLVED' => [
                'EMAIL' => ['addressee' => 'Sophie Martin', 'petName' => 'Milo', 'sentence' => 'Votre demande de renouvellement d\'ordonnance pour Milo a été traitée.', 'staffMessage' => 'Ordonnance prête.'],
                'SMS'   => ['sentence' => 'Votre demande de modification pour Milo a été traitée.', 'staffMessage' => ''],
            ],
            'PORTAL_BOOKING_DECLINED' => [
                'SMS' => ['clinicName' => 'Clinique Montmartre', 'slotLabel' => 'jeudi 22/10 à 9h30'],
            ],
        ];
    }

    /**
     * @return iterable<string, array{MessagePurpose}>
     */
    public static function provideEveryTemplateRendersWithTheReferenceDataCases(): iterable
    {
        foreach (MessagePurpose::cases() as $purpose) {
            yield $purpose->value => [$purpose];
        }
    }

    /**
     * AC 6: every SMS template rendered with the reference data is GSM-7 and fits in one segment.
     */
    #[DataProvider('provideEveryTemplateRendersWithTheReferenceDataCases')]
    public function testEveryTemplateRendersWithTheReferenceData(MessagePurpose $purpose): void
    {
        foreach (Channel::cases() as $channel) {
            if (!TemplateCatalog::supports($purpose, $channel)) {
                continue;
            }

            $content = $this->render($purpose, $channel, self::reference()[$purpose->value][$channel->value]);

            if (Channel::SMS === $channel) {
                self::assertSame(SmsEncoding::GSM_7, $content->encoding, $purpose->value);
                // The Portal invitation carries a full link: it takes two segments.
                self::assertSame(MessagePurpose::PORTAL_INVITATION === $purpose ? 2 : 1, $content->segments, $purpose->value . ': ' . $content->smsText);
                self::assertTrue(Gsm7::isGsm7((string) $content->smsText));

                continue;
            }

            self::assertNotSame('', $content->subject);
            self::assertStringNotContainsString('<style', (string) $content->html);
            self::assertStringContainsString('style="', (string) $content->html, 'CSS inlined');
            self::assertStringNotContainsString('{{', (string) $content->text);
            self::assertStringNotContainsString('&#039;', (string) $content->subject);
        }
    }

    public function testTheSmsWordingIsTheReferenceOne(): void
    {
        $sms = static fn (MessagePurpose $purpose): ?string => self::renderStatic($purpose, Channel::SMS, self::reference()[$purpose->value]['SMS'])->smsText;

        self::assertSame('Clinique Montmartre : RDV confirmé pour Milo le jeudi 22/10 à 9h30 avec le Dr Rousseau. Pour le modifier ou l\'annuler : 01 42 64 18 00', $sms(MessagePurpose::APPOINTMENT_CONFIRMATION));
        self::assertSame('Clinique Montmartre : le RDV de Milo est déplacé au vendredi 23/10 à 11h00, avec le Dr Rousseau (au lieu du jeudi 22/10 à 9h30). Infos : 01 42 64 18 00', $sms(MessagePurpose::APPOINTMENT_CHANGE));
        self::assertSame('Clinique Montmartre : le RDV de Milo du jeudi 22/10 à 9h30 est annulé. Pour en fixer un nouveau : 01 42 64 18 00', $sms(MessagePurpose::APPOINTMENT_CANCELLATION));
        self::assertSame('Clinique Montmartre : votre demande de rendez-vous du jeudi 22/10 à 9h30 n\'a pas été confirmée. Choisissez un autre créneau dans l\'app Kiveto.', $sms(MessagePurpose::APPOINTMENT_DECLINED));
        self::assertSame('Clinique Montmartre : rappel du RDV de Milo demain à 9h30 avec le Dr Rousseau. En cas d\'absence, merci de nous prévenir au 01 42 64 18 00', $sms(MessagePurpose::APPOINTMENT_REMINDER));
        self::assertSame('Clinique Montmartre : la facture FA-2026-0702 du 04/09 (64,50 €) reste à régler. Vous pouvez la régler à la clinique. Infos : 01 42 64 18 00', $sms(MessagePurpose::INVOICE_REMINDER));
        self::assertSame('Kiveto : message de test envoyé le 26/10/2026 à 10:42. Aucune action n\'est nécessaire.', $sms(MessagePurpose::TEST));
        self::assertSame('Kiveto : votre code est 123456. Il expire dans 10 minutes. Ne le communiquez à personne.', $sms(MessagePurpose::PORTAL_OTP));
        self::assertSame('Clinique Montmartre : votre demande de rendez-vous du jeudi 22/10 à 9h30 n\'a pas été confirmée. Choisissez un autre créneau dans l\'app Kiveto.', $sms(MessagePurpose::PORTAL_BOOKING_DECLINED));
        self::assertSame('Clinique Montmartre vous invite sur Kiveto pour suivre vos animaux : ' . self::PORTAL_LINK . ' (valable jusqu\'au 05/11/2026)', $sms(MessagePurpose::PORTAL_INVITATION));
    }

    /** AC 61, P6: an action code says what it confirms; the e-mail-change messages are Kiveto's. */
    public function testThePortalActionCodesAndEmailChangeMessagesHaveTheirWording(): void
    {
        $code = static fn (string $purpose): RenderedContent => self::renderStatic(MessagePurpose::PORTAL_OTP, Channel::SMS, ['code' => '123456', 'validityMinutes' => 10, 'purpose' => $purpose]);

        self::assertSame('Kiveto : votre code est 123456. Il expire dans 10 minutes. Ne le communiquez à personne.', $code('invitation')->smsText);
        self::assertSame('Code de confirmation de changement d\'e-mail de votre compte Kiveto : 123456. Il expire dans 10 minutes. Ne le communiquez à personne.', $code('email_change')->smsText);
        self::assertSame('Code de confirmation de suppression de votre compte Kiveto : 123456. Il expire dans 10 minutes. Ne le communiquez à personne.', $code('account_deletion')->smsText);
        self::assertSame([SmsEncoding::GSM_7, 1, 1], [$code('email_change')->encoding, $code('email_change')->segments, $code('account_deletion')->segments]);

        $login = $this->render(MessagePurpose::PORTAL_OTP, Channel::EMAIL, self::reference()['PORTAL_OTP']['EMAIL']);
        self::assertSame('Votre code de connexion Kiveto', $login->subject);
        self::assertStringContainsString('123456', (string) $login->text);

        $verification = $this->render(MessagePurpose::PORTAL_EMAIL_VERIFICATION, Channel::EMAIL, self::reference()['PORTAL_EMAIL_VERIFICATION']['EMAIL']);
        self::assertSame('Confirmez votre adresse e-mail Kiveto', $verification->subject);
        self::assertStringContainsString('Il expire dans 10 minutes.', (string) $verification->text);

        $notice = $this->render(MessagePurpose::PORTAL_EMAIL_CHANGED_NOTICE, Channel::EMAIL, self::reference()['PORTAL_EMAIL_CHANGED_NOTICE']['EMAIL']);
        self::assertSame('L\'adresse e-mail de votre compte Kiveto a changé', $notice->subject);
        self::assertStringContainsString('Le 01/11/2026 à 14:05, l\'adresse e-mail de votre compte Kiveto a été remplacée par n***@example.com.', (string) $notice->text);
        self::assertStringNotContainsString('Clinique Montmartre', (string) $notice->html, 'sent by Kiveto, not by a clinic');
    }

    /** AC 63: the export notice names its deadline and carries no link (the archive is downloaded in the app). */
    public function testTheDataExportNoticeCarriesNoLink(): void
    {
        $notice = $this->render(MessagePurpose::PORTAL_DATA_EXPORT_READY, Channel::EMAIL, self::reference()['PORTAL_DATA_EXPORT_READY']['EMAIL']);

        self::assertSame('Votre export de données Kiveto est prêt', $notice->subject);
        self::assertStringContainsString('Bonjour Sophie,', (string) $notice->text);
        self::assertStringContainsString('Il reste disponible jusqu\'au 04/11/2026 à 11:02, puis il est supprimé.', (string) $notice->text);
        self::assertStringNotContainsString('http', (string) $notice->text);
        self::assertStringNotContainsString('href="http', (string) $notice->html);
        self::assertStringNotContainsString('Clinique Montmartre', (string) $notice->html, 'sent by Kiveto, not by a clinic');
    }

    /** AC 230: the fixed sentence, then the team's message when there is one. */
    public function testTheAnswerToAPortalRequestCarriesTheSentenceAndTheTeamsMessage(): void
    {
        $email = $this->render(MessagePurpose::PORTAL_REQUEST_RESOLVED, Channel::EMAIL, self::reference()['PORTAL_REQUEST_RESOLVED']['EMAIL']);
        self::assertSame('Votre demande pour Milo', $email->subject);
        self::assertStringContainsString('Votre demande de renouvellement d\'ordonnance pour Milo a été traitée.', (string) $email->text);
        self::assertStringContainsString("Message de l'équipe :\nOrdonnance prête.", (string) $email->text);
        self::assertStringContainsString('Clinique Montmartre', (string) $email->html, 'sent by the clinic');

        $without = $this->render(MessagePurpose::PORTAL_REQUEST_RESOLVED, Channel::EMAIL, ['staffMessage' => ''] + self::reference()['PORTAL_REQUEST_RESOLVED']['EMAIL']);
        self::assertStringNotContainsString('Message de l\'équipe', (string) $without->text);

        $sms = static fn (string $message): ?string => self::renderStatic(MessagePurpose::PORTAL_REQUEST_RESOLVED, Channel::SMS, ['sentence' => 'Votre demande de modification pour Milo n\'a pas pu être acceptée.', 'staffMessage' => $message])->smsText;
        // GSM-7: « ê » is transliterated
        self::assertSame('Clinique Montmartre : Votre demande de modification pour Milo n\'a pas pu etre acceptée.', $sms(''));
        self::assertSame('Clinique Montmartre : Votre demande de modification pour Milo n\'a pas pu etre acceptée. Pesez-le à la clinique.', $sms('Pesez-le à la clinique.'));
    }

    /**
     * AC 6: « Chloë » at « Côte d'Opale » → « Chloe », « Cote d'Opale », GSM-7, one segment.
     */
    public function testVariableDataIsTransliterated(): void
    {
        $profile = ClinicMessagingProfile::of('01960000-0000-7000-8000-00000000c0a1', 'Côte d’Opale', 'cote-opale', null, 'Opale', 500, '1 quai', 'Calais', '03 21 00 00 00', null, null);
        $content = self::renderStatic(MessagePurpose::APPOINTMENT_CONFIRMATION, Channel::SMS, ['animalName' => 'Chloë', 'slotLabel' => 'jeudi 22/10 à 9h30', 'practitionerName' => ''], $profile);

        self::assertSame("Cote d'Opale : RDV confirmé pour Chloe le jeudi 22/10 à 9h30. Pour le modifier ou l'annuler : 03 21 00 00 00", $content->smsText);
        self::assertSame(SmsEncoding::GSM_7, $content->encoding);
        self::assertSame(1, $content->segments);
    }

    public function testAClinicEmailShowsTheClinicCardAndFooter(): void
    {
        $content = $this->render(MessagePurpose::APPOINTMENT_CONFIRMATION, Channel::EMAIL, self::reference()['APPOINTMENT_CONFIRMATION']['EMAIL']);

        self::assertSame('Rendez-vous confirmé — Milo, jeudi 22 octobre à 9h30', $content->subject);
        self::assertNull($content->inlineLogoKey);
        $html = (string) $content->html;
        self::assertStringContainsString('Bonjour Madame Dubois,', $html);
        self::assertStringContainsString('Le rendez-vous de <strong>Milo</strong> est confirmé.', $html);
        self::assertStringContainsString('Vaccination', $html);
        self::assertStringContainsString('Clinique Montmartre · 18 rue Lepic, 75018 Paris · 01 42 64 18 00', $html);
        self::assertStringContainsString('Paris 18e', $html);
        self::assertStringContainsString('background-color: #4338CA', $html, 'the clinic brand colour');
        self::assertStringContainsString('Vous recevez ce message suite à la prise de votre rendez-vous.', $html);
        self::assertStringContainsString('Pour modifier ou annuler, appelez-nous au 01 42 64 18 00.', (string) $content->text);
    }

    public function testTheDeclineEmailCarriesTheP7Sentence(): void
    {
        $content = $this->render(MessagePurpose::APPOINTMENT_DECLINED, Channel::EMAIL, self::reference()['APPOINTMENT_DECLINED']['EMAIL']);

        self::assertSame('Demande de rendez-vous non confirmée — Milo, jeudi 22 octobre à 9h30', $content->subject);
        $sentence = "Votre demande de rendez-vous du jeudi 22 octobre à 9h30 n'a pas pu être confirmée — choisissez un autre créneau dans l'app Kiveto.";
        self::assertStringContainsString($sentence, (string) $content->text);
        self::assertStringContainsString($sentence, (string) $content->html);
        self::assertStringContainsString('Vétérinaire : Dr Rousseau', (string) $content->text);
    }

    public function testTheLogoIsReferencedByContentIdWhenItsFileExists(): void
    {
        $storage = self::getContainer()->get(FileStorageInterface::class);
        \assert($storage instanceof FileStorageInterface);
        $storage->storeAt(self::LOGO_KEY, 'PNG');
        $storage->storeAt('notification-test/clinic-logo.svg', '<svg/>');

        try {
            $content = self::renderStatic(MessagePurpose::INVOICE_SENT, Channel::EMAIL, self::reference()['INVOICE_SENT']['EMAIL'], InMemoryProfiles::montmartre(logo: self::LOGO_KEY));
            $svg     = self::renderStatic(MessagePurpose::INVOICE_SENT, Channel::EMAIL, self::reference()['INVOICE_SENT']['EMAIL'], InMemoryProfiles::montmartre(logo: 'notification-test/clinic-logo.svg'));
        } finally {
            $storage->delete(self::LOGO_KEY);
            $storage->delete('notification-test/clinic-logo.svg');
        }

        self::assertNull($svg->inlineLogoKey, 'mail clients do not show an SVG inline: the initial is used');
        self::assertStringNotContainsString('cid:', (string) $svg->html);

        self::assertSame(self::LOGO_KEY, $content->inlineLogoKey);
        self::assertStringContainsString('src="cid:' . RenderedContent::INLINE_LOGO_CID . '"', (string) $content->html);

        $missing = self::renderStatic(MessagePurpose::INVOICE_SENT, Channel::EMAIL, self::reference()['INVOICE_SENT']['EMAIL'], InMemoryProfiles::montmartre(logo: 'notification-test/missing.png'));
        self::assertNull($missing->inlineLogoKey, 'a missing logo file falls back to the initial');
        self::assertStringNotContainsString('cid:', (string) $missing->html);
    }

    public function testAnInvoiceEmailHasTwoVariants(): void
    {
        $due = $this->render(MessagePurpose::INVOICE_SENT, Channel::EMAIL, self::reference()['INVOICE_SENT']['EMAIL']);
        self::assertSame('Votre facture FA-2026-0814 — Clinique Montmartre', $due->subject);
        self::assertStringContainsString('Reste dû', (string) $due->html);
        self::assertStringContainsString('Dont TVA 20 %', (string) $due->html);
        self::assertStringNotContainsString('en ligne', (string) $due->html, 'no payment link');

        $settled = $this->render(MessagePurpose::INVOICE_SENT, Channel::EMAIL, ['balanceDue' => ''] + self::reference()['INVOICE_SENT']['EMAIL']);
        self::assertStringContainsString('Réglée', (string) $settled->html);
        self::assertStringContainsString('Statut : Réglée', (string) $settled->text);
    }

    public function testAccountEmailsAreSignedKiveto(): void
    {
        $invitation = $this->render(MessagePurpose::STAFF_INVITATION, Channel::EMAIL, self::reference()['STAFF_INVITATION']['EMAIL']);

        self::assertSame('Dr Claire Lambert vous invite à rejoindre Clinique Montmartre sur Kiveto', $invitation->subject);
        self::assertStringContainsString('Kiveto · logiciel de gestion vétérinaire', (string) $invitation->html);
        self::assertStringContainsString('href="http://clinic.kiveto.local/invitation/abc"', (string) $invitation->html);
        self::assertStringNotContainsString('double authentification', (string) $invitation->html);
        self::assertMatchesRegularExpression('#class="initial"[^>]*>M</span>#', (string) $invitation->html, 'the initial of « Clinique Montmartre »');
        self::assertStringContainsString('Bonjour Hugo,', (string) $invitation->text);

        $reset = $this->render(MessagePurpose::PASSWORD_RESET, Channel::EMAIL, self::reference()['PASSWORD_RESET']['EMAIL']);
        self::assertSame('Réinitialisation de votre mot de passe Kiveto', $reset->subject);
        self::assertStringContainsString('Demande reçue le 16/10/2026 à 08:14.', (string) $reset->html);
    }

    public function testThePortalInvitationEmailIsSignedKivetoAndNamesTheClinic(): void
    {
        $invitation = $this->render(MessagePurpose::PORTAL_INVITATION, Channel::EMAIL, self::reference()['PORTAL_INVITATION']['EMAIL']);

        self::assertSame('Clinique Montmartre vous invite sur Kiveto', $invitation->subject);
        self::assertNull($invitation->inlineLogoKey);
        self::assertStringContainsString('Kiveto · logiciel de gestion vétérinaire', (string) $invitation->html);
        self::assertStringContainsString('href="' . self::PORTAL_LINK . '"', (string) $invitation->html);
        self::assertStringContainsString("valable jusqu'au 05/11/2026", (string) $invitation->html);
        self::assertStringNotContainsString('18 rue Lepic', (string) $invitation->html, 'no clinic footer: Kiveto sends it');
        self::assertStringContainsString('Bonjour Sophie,', (string) $invitation->text);
        self::assertStringContainsString("Accepter l'invitation : " . self::PORTAL_LINK, (string) $invitation->text);

        $anonymous = $this->render(MessagePurpose::PORTAL_INVITATION, Channel::EMAIL, ['ownerFirstName' => ''] + self::reference()['PORTAL_INVITATION']['EMAIL']);
        self::assertStringContainsString('Bonjour,', (string) $anonymous->text);
    }

    public function testTheConsultationNoteDependsOnThePrescription(): void
    {
        $with = $this->render(MessagePurpose::CONSULTATION_DOCUMENTS, Channel::EMAIL, self::reference()['CONSULTATION_DOCUMENTS']['EMAIL']);
        self::assertSame('Consultation de Minou du 16 octobre 2026 — vos documents', $with->subject);
        self::assertStringContainsString("Conservez l'ordonnance", (string) $with->text);
        self::assertStringContainsString('FA-2026-0814 · 88,70 €', (string) $with->html);

        $without = $this->render(MessagePurpose::CONSULTATION_DOCUMENTS, Channel::EMAIL, ['documentLabels' => ['Compte rendu'], 'documentValues' => ['Joint']] + self::reference()['CONSULTATION_DOCUMENTS']['EMAIL']);
        self::assertStringNotContainsString("Conservez l'ordonnance", (string) $without->text);
    }

    public function testAClinicPurposeNeedsTheProfile(): void
    {
        $this->expectException(\LogicException::class);

        $this->renderer()->render(MessagePurpose::APPOINTMENT_REMINDER, Channel::SMS, self::reference()['APPOINTMENT_REMINDER']['SMS'], null);
    }

    /**
     * @param array<string, scalar|list<scalar>> $parameters
     */
    private function render(MessagePurpose $purpose, Channel $channel, array $parameters): RenderedContent
    {
        return self::renderStatic($purpose, $channel, $parameters);
    }

    /**
     * @param array<string, scalar|list<scalar>> $parameters
     */
    private static function renderStatic(MessagePurpose $purpose, Channel $channel, array $parameters, ?ClinicMessagingProfile $profile = null): RenderedContent
    {
        return self::renderer()->render($purpose, $channel, $parameters, $profile ?? InMemoryProfiles::montmartre());
    }

    private static function renderer(): MessageRendererInterface
    {
        $renderer = self::getContainer()->get('test.' . MessageRendererInterface::class);
        \assert($renderer instanceof MessageRendererInterface);

        return $renderer;
    }
}
