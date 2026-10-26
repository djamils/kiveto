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
                self::assertSame(1, $content->segments, $purpose->value . ': ' . $content->smsText);
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
        self::assertSame('Clinique Montmartre : rappel du RDV de Milo demain à 9h30 avec le Dr Rousseau. En cas d\'absence, merci de nous prévenir au 01 42 64 18 00', $sms(MessagePurpose::APPOINTMENT_REMINDER));
        self::assertSame('Clinique Montmartre : la facture FA-2026-0702 du 04/09 (64,50 €) reste à régler. Vous pouvez la régler à la clinique. Infos : 01 42 64 18 00', $sms(MessagePurpose::INVOICE_REMINDER));
        self::assertSame('Kiveto : message de test envoyé le 26/10/2026 à 10:42. Aucune action n\'est nécessaire.', $sms(MessagePurpose::TEST));
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
