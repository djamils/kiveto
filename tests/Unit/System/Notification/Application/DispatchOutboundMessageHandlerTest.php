<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Application;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\System\Notification\Application\Command\DispatchOutboundMessage\DispatchOutboundMessage;
use App\System\Notification\Application\Command\DispatchOutboundMessage\DispatchOutboundMessageHandler;
use App\System\Notification\Application\Command\RecordDispatchOutcome\RecordDispatchOutcome;
use App\System\Notification\Application\Exception\DeliveryRefusedException;
use App\System\Notification\Application\Service\AttachmentFilename;
use App\System\Notification\Domain\Message\Attachment;
use App\System\Notification\Domain\Message\AttachmentOwner;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\IdempotencyKey;
use App\System\Notification\Domain\Message\MessagePurpose;
use App\System\Notification\Domain\Message\OutboundMessage;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\System\Notification\Domain\Message\Recipient;
use App\System\Notification\Domain\Message\RenderedContent;
use App\System\Notification\Domain\Message\SenderIdentity;
use App\System\Notification\Domain\Message\SubjectReference;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\Notification\Support\CapturingBuses;
use App\Tests\Unit\System\Notification\Support\FakeSenders;
use App\Tests\Unit\System\Notification\Support\InMemoryFiles;
use App\Tests\Unit\System\Notification\Support\InMemoryMessages;
use App\Tests\Unit\System\Notification\Support\Messages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\BufferingLogger;

final class DispatchOutboundMessageHandlerTest extends TestCase
{
    private const string EMAIL_ID = '01960000-0000-7000-8000-000000000e01';
    private InMemoryMessages $messages;
    private FakeSenders $senders;
    private InMemoryFiles $files;
    private CapturingBuses $buses;
    private BufferingLogger $logger;
    private DispatchOutboundMessageHandler $handler;

    protected function setUp(): void
    {
        $this->messages = new InMemoryMessages();
        $this->senders  = new FakeSenders();
        $this->files    = new InMemoryFiles();
        $this->buses    = new CapturingBuses();
        $this->logger   = new BufferingLogger();
        $this->handler  = new DispatchOutboundMessageHandler(
            $this->messages,
            $this->senders,
            $this->senders,
            $this->files,
            $this->buses,
            new FrozenClock(new \DateTimeImmutable('2026-10-16T10:00:00+00:00')),
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        $this->logger->cleanLogs();
    }

    /**
     * AC 2 (dispatch side): sender, reply-to, recipient, body, the attached
     * PDF read from the storage, the inline logo and the idempotency tag.
     */
    public function testAnEmailIsHandedOverWithItsFilesAndTheOutcomeRecorded(): void
    {
        $this->messages->save($this->emailWithLogo('clinic/logo.PNG', [
            Attachment::of('billing/sent/invoice/inv-1.pdf', 'FA-2026-0814.pdf', 'application/pdf', AttachmentOwner::REQUESTER),
            Attachment::of('notification/attachments/abc/report.pdf', 'Compte rendu – Milo.pdf', 'application/pdf', AttachmentOwner::NOTIFICATION),
        ]));
        $this->files->storeAt('billing/sent/invoice/inv-1.pdf', '%PDF-invoice');
        $this->files->storeAt('notification/attachments/abc/report.pdf', '%PDF-report');
        $this->files->storeAt('clinic/logo.PNG', 'PNG-bytes');

        ($this->handler)(new DispatchOutboundMessage(self::EMAIL_ID));

        self::assertCount(1, $this->senders->emails);
        $email = $this->senders->emails[0];
        self::assertSame(['Clinique Montmartre', 'clinique-montmartre@mail.kiveto.fr', 'accueil@clinique-montmartre.fr'], [$email->fromName, $email->fromAddress, $email->replyTo]);
        self::assertSame(['t.lefebvre@orange.fr', 'Thomas Lefebvre'], [$email->toAddress, $email->toName]);
        self::assertSame('Votre facture FA-2026-0814 — Clinique Montmartre', $email->subject);
        self::assertSame('<p><img src="cid:logo.png" alt=""> Bonjour</p>', $email->html, 'the cid names the inline part with its extension');
        self::assertSame('Bonjour', $email->text);
        self::assertSame('invoice:1:send:1', $email->tag);
        self::assertSame(
            [['FA-2026-0814.pdf', '%PDF-invoice', 'application/pdf'], ['Compte-rendu-Milo.pdf', '%PDF-report', 'application/pdf']],
            array_map(static fn ($a): array => [$a->filename, $a->content, $a->mime], $email->attachments),
        );
        self::assertSame(['logo.png', 'PNG-bytes', 'image/png'], [$email->inlineLogo?->filename, $email->inlineLogo?->content, $email->inlineLogo?->mime]);

        $outcomes = $this->buses->commandsOf(RecordDispatchOutcome::class);
        self::assertCount(1, $outcomes);
        self::assertSame([self::EMAIL_ID, 'brevo-email-1', null, '2026-10-16T10:00:00+00:00'], [$outcomes[0]->messageId, $outcomes[0]->externalId, $outcomes[0]->error, $outcomes[0]->occurredAt]);
    }

    public function testAnSmsIsHandedOverFromTheClinicSender(): void
    {
        $this->messages->save(Messages::sms());

        ($this->handler)(new DispatchOutboundMessage('01960000-0000-7000-8000-000000000501'));

        self::assertCount(1, $this->senders->sms);
        $sms = $this->senders->sms[0];
        self::assertSame(['Montmartre', '+33612345678', 'Clinique Montmartre : rappel du RDV de Milo demain a 9h30.', 'appointment:1:reminder'], [$sms->from, $sms->to, $sms->text, $sms->tag]);
        self::assertSame('brevo-sms-1', $this->buses->commandsOf(RecordDispatchOutcome::class)[0]->externalId);
    }

    public function testOnlyAQueuedMessageIsSent(): void
    {
        $message = Messages::email();
        $message->markSent('already', new \DateTimeImmutable('2026-10-16 08:01:00'));
        $this->messages->save($message);

        ($this->handler)(new DispatchOutboundMessage(self::EMAIL_ID));
        ($this->handler)(new DispatchOutboundMessage('01960000-0000-7000-8000-00000000dead'));

        self::assertSame([], $this->senders->emails);
        self::assertSame([], $this->buses->commands);
        self::assertStringContainsString('no such message', $this->firstLog());
    }

    /**
     * AC 4: a refusal for good is recorded FAILED with its reason, no retry.
     */
    public function testARefusalIsRecordedAsAFailure(): void
    {
        $this->messages->save(Messages::sms());
        $this->senders->failure = new DeliveryRefusedException('Refusé par le fournisseur (HTTP 400) : invalid sender');

        ($this->handler)(new DispatchOutboundMessage('01960000-0000-7000-8000-000000000501'));

        $outcome = $this->buses->commandsOf(RecordDispatchOutcome::class)[0];
        self::assertSame([null, 'Refusé par le fournisseur (HTTP 400) : invalid sender'], [$outcome->externalId, $outcome->error]);
    }

    /**
     * AC 4: a temporary failure is rethrown for a Messenger retry, nothing recorded.
     */
    public function testATemporaryFailureIsLeftToTheRetries(): void
    {
        $this->messages->save(Messages::sms());
        $this->senders->failure = ExternalServiceUnavailable::rateLimited('Brevo', 60);

        try {
            ($this->handler)(new DispatchOutboundMessage('01960000-0000-7000-8000-000000000501'));
            self::fail('The failure must reach Messenger.');
        } catch (ExternalServiceUnavailable $e) {
            self::assertSame(60, $e->retryAfter());
        }

        self::assertSame([], $this->buses->commands);
    }

    public function testAnAttachmentThatCannotBeReadFailsTheMessage(): void
    {
        $this->messages->save(Messages::email());
        $this->files->storeAt('clinic/logo.png', 'PNG');

        ($this->handler)(new DispatchOutboundMessage(self::EMAIL_ID));

        self::assertSame([], $this->senders->emails);
        self::assertSame('Pièce jointe introuvable : FA-2026-0814.pdf', $this->buses->commandsOf(RecordDispatchOutcome::class)[0]->error);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideAnEmailGoesWithoutALogoItCannotEmbedCases(): iterable
    {
        yield 'file gone' => ['clinic/logo.png', false];
        yield 'not shown inline by mail clients' => ['clinic/logo.svg', true];
    }

    #[DataProvider('provideAnEmailGoesWithoutALogoItCannotEmbedCases')]
    public function testAnEmailGoesWithoutALogoItCannotEmbed(string $key, bool $stored): void
    {
        $this->messages->save($this->emailWithLogo($key, []));
        if ($stored) {
            $this->files->storeAt($key, '<svg/>');
        }

        ($this->handler)(new DispatchOutboundMessage(self::EMAIL_ID));

        self::assertNull($this->senders->emails[0]->inlineLogo);
        self::assertSame('<p><img src="cid:logo" alt=""> Bonjour</p>', $this->senders->emails[0]->html);
        self::assertStringContainsString('without its inline logo', $this->firstLog());
    }

    public function testAnEmailWithoutLogoOrReplyToIsSentAsIs(): void
    {
        $this->messages->save(OutboundMessage::queue(
            OutboundMessageId::fromString(self::EMAIL_ID),
            null,
            null,
            MessagePurpose::TEST,
            Channel::EMAIL,
            Recipient::for(Channel::EMAIL, 'claire@kiveto.fr', ''),
            SenderIdentity::kiveto('no-reply@kiveto.fr'),
            SubjectReference::of('clinic', 'c-1'),
            IdempotencyKey::fromString('test:1'),
            RenderedContent::email('Message de test Kiveto', '<p>Test</p>', 'Test'),
            [],
            new \DateTimeImmutable('2026-10-16 08:00:00'),
        ));

        ($this->handler)(new DispatchOutboundMessage(self::EMAIL_ID));

        $email = $this->senders->emails[0];
        self::assertSame(['Kiveto', 'no-reply@kiveto.fr', null, null, []], [$email->fromName, $email->fromAddress, $email->replyTo, $email->inlineLogo, $email->attachments]);
    }

    public function testAQueuedMessageWithoutContentIsABug(): void
    {
        $this->messages->save(OutboundMessage::reconstitute(
            OutboundMessageId::fromString(self::EMAIL_ID),
            null,
            null,
            MessagePurpose::TEST,
            Channel::SMS,
            null,
            null,
            SubjectReference::of('clinic', 'c-1'),
            IdempotencyKey::fromString('test:1'),
            null,
            [],
            DeliveryStatus::QUEUED,
            null,
            [],
            null,
            new \DateTimeImmutable('2026-10-16 08:00:00'),
            null,
            new \DateTimeImmutable('2026-10-16 08:00:00'),
            null,
        ));

        $this->expectException(\LogicException::class);

        ($this->handler)(new DispatchOutboundMessage(self::EMAIL_ID));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideAttachmentNamesAreSanitizedCases(): iterable
    {
        yield 'plain' => ['FA-2026-0814.pdf', 'FA-2026-0814.pdf'];
        yield 'accents and spaces' => ['Ordonnance – Chloë Dupré.pdf', 'Ordonnance-Chloe-Dupre.pdf'];
        yield 'path and quotes' => ['../"rapport".pdf', 'rapport-.pdf'];
        yield 'nothing left' => ['……', AttachmentFilename::FALLBACK];
    }

    #[DataProvider('provideAttachmentNamesAreSanitizedCases')]
    public function testAttachmentNamesAreSanitized(string $raw, string $safe): void
    {
        self::assertSame($safe, AttachmentFilename::safe($raw));
    }

    private function firstLog(): string
    {
        $logs    = $this->logger->cleanLogs();
        $message = \is_array($logs[0] ?? null) ? $logs[0][1] ?? null : null;
        \assert(\is_string($message));

        return $message;
    }

    /**
     * @param list<Attachment> $attachments
     */
    private function emailWithLogo(string $logoKey, array $attachments): OutboundMessage
    {
        return OutboundMessage::queue(
            OutboundMessageId::fromString(self::EMAIL_ID),
            Messages::CLINIC,
            Messages::CLIENT,
            MessagePurpose::INVOICE_SENT,
            Channel::EMAIL,
            Recipient::for(Channel::EMAIL, 't.lefebvre@orange.fr', 'Thomas Lefebvre'),
            SenderIdentity::clinic('Clinique Montmartre', 'clinique-montmartre', 'mail.kiveto.fr', 'accueil@clinique-montmartre.fr', 'Montmartre'),
            SubjectReference::of('invoice', 'inv-1'),
            IdempotencyKey::fromString('invoice:1:send:1'),
            RenderedContent::email('Votre facture FA-2026-0814 — Clinique Montmartre', '<p><img src="cid:logo" alt=""> Bonjour</p>', 'Bonjour', $logoKey),
            $attachments,
            new \DateTimeImmutable('2026-10-16 08:00:00'),
        );
    }
}
