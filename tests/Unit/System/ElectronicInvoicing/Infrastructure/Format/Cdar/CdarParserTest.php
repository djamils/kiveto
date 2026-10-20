<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Format\Cdar;

use App\System\ElectronicInvoicing\Application\Exception\UnparsableLifecycleMessageException;
use App\System\ElectronicInvoicing\Infrastructure\Format\Cdar\CdarParser;
use App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Format\AfnorSamplesXsdTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * AC 21: every AFNOR CDV sample (copied into
 * tests/Fixtures/electronic-invoicing/afnor, « - Copia » duplicates excluded)
 * parses to the status in its file name.
 */
final class CdarParserTest extends TestCase
{
    private const array REASON_CODES = [
        'UC2_F202500004_02-CDV-213_Rejetee.xml'          => 'DOUBLON',
        'UC2_F202500004_02-CDV-213_Rejetee_POUR_PPF.xml' => 'DOUBLON',
        'UC3_F202500005_04-CDV-210_Refusee.xml'          => 'TX_TVA_ERR',
        'UC3_F202500005_04-CDV-210_Refusee_POUR_PPF.xml' => 'TX_TVA_ERR',
        'UC4_F202500006_04-CDV-207_En_litige.xml'        => 'TX_TVA_ERR',
        'UC5_F202500007_04-CDV-207_En_litige.xml'        => 'TX_TVA_ERR',
    ];

    #[DataProvider('provideParsesTheAfnorCdvSampleCases')]
    public function testParsesTheAfnorCdvSample(string $file): void
    {
        $content = file_get_contents($file);
        self::assertIsString($content);
        self::assertSame(1, preg_match('/_(F\d+)_\d+-CDV-(\d{3})_/', basename($file), $matches));

        $messages = new CdarParser()->parse($content);

        self::assertCount(1, $messages);
        $message = $messages[0];
        self::assertSame((int) $matches[2], $message->statusCode);
        self::assertSame($matches[1], $message->invoiceNumber);
        self::assertSame('100000009', $message->sellerSiren);
        self::assertSame('380', $message->typeCode);
        self::assertSame(self::REASON_CODES[basename($file)] ?? null, $message->reasonCode);
        self::assertNotNull($message->invoiceIssueDate);
        self::assertSame('Europe/Paris', $message->issuedAt->getTimezone()->getName());
    }

    public function testEveryAfnorCdvSampleIsCovered(): void
    {
        self::assertCount(59, self::cdvSamples());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideParsesTheAfnorCdvSampleCases(): iterable
    {
        foreach (self::cdvSamples() as $file) {
            yield basename($file) => [$file];
        }
    }

    public function testReadsDatesReasonAndNote(): void
    {
        $messages = new CdarParser()->parse(self::sample('UC2/UC2_F202500004_02-CDV-213_Rejetee.xml'));

        $message = $messages[0];
        self::assertSame(213, $message->statusCode);
        self::assertSame('DOUBLON', $message->reasonCode);
        self::assertSame('Facture en doublon (déjà émise / réçue)', $message->reasonText);
        self::assertSame('2025-07-01 00:00:00 +02:00', $message->invoiceIssueDate?->format('Y-m-d H:i:s P'));
        self::assertSame('2025-07-01 16:15:00 +02:00', $message->issuedAt->format('Y-m-d H:i:s P'));
    }

    public function testReasonTextIsTrimmed(): void
    {
        $message = new CdarParser()->parse(self::sample('UC3/UC3_F202500005_04-CDV-210_Refusee.xml'))[0];

        self::assertSame('Taux de TVA erroné', $message->reasonText);
    }

    public function testStatusWithoutReasonHasNoReason(): void
    {
        $message = new CdarParser()->parse(self::sample('UC1/UC1_F202500003_01-CDV-200_Deposee.xml'))[0];

        self::assertSame(200, $message->statusCode);
        self::assertNull($message->reasonCode);
        self::assertNull($message->reasonText);
    }

    public function testFallsBackToTheNoteWhenNoReasonIsGiven(): void
    {
        $message = new CdarParser()->parse(self::message(status: '<ReasonCode>NON_TRANSMISE</ReasonCode><IncludedNote><Content>Destinataire non joignable</Content></IncludedNote>'))[0];

        self::assertSame('NON_TRANSMISE', $message->reasonCode);
        self::assertSame('Destinataire non joignable', $message->reasonText);
    }

    public function testIsPrefixAndNamespaceTolerantAndKeysOnProcessConditionCode(): void
    {
        $messages = new CdarParser()->parse(self::message(
            references: self::reference('F1', '501', '<StatusCode>45</StatusCode>') . self::reference('F2', '200'),
        ));

        self::assertCount(2, $messages);
        self::assertSame(501, $messages[0]->statusCode);
        self::assertSame('F1', $messages[0]->invoiceNumber);
        self::assertSame(200, $messages[1]->statusCode);
        self::assertSame('F2', $messages[1]->invoiceNumber);
        self::assertNull($messages[1]->invoiceIssueDate);
        self::assertNull($messages[1]->typeCode);
        self::assertNull($messages[1]->sellerSiren);
    }

    public function testHeaderDateTimeWithAnOffsetKeepsIt(): void
    {
        $message = new CdarParser()->parse(self::message(header: '<DateTime>2026-10-20T08:30:00+00:00</DateTime>'))[0];

        self::assertSame('2026-10-20T08:30:00+00:00', $message->issuedAt->format(\DATE_ATOM));
    }

    public function testHeaderDateTimeWithoutOffsetIsParisTime(): void
    {
        $message = new CdarParser()->parse(self::message(header: '<DateTime>2026-01-20T08:30:00</DateTime>'))[0];

        self::assertSame('2026-01-20T08:30:00+01:00', $message->issuedAt->format(\DATE_ATOM));
    }

    public function testHeaderDateTimeInFormat102IsMidnightParisTime(): void
    {
        $message = new CdarParser()->parse(self::message(header: '<DateTimeString format="102">20261020</DateTimeString>'))[0];

        self::assertSame('2026-10-20T00:00:00+02:00', $message->issuedAt->format(\DATE_ATOM));
    }

    #[DataProvider('provideRefusesUnparsableMessagesCases')]
    public function testRefusesUnparsableMessages(string $content, string $reason): void
    {
        $this->expectException(UnparsableLifecycleMessageException::class);
        $this->expectExceptionMessage($reason);

        new CdarParser()->parse($content);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRefusesUnparsableMessagesCases(): iterable
    {
        yield 'empty' => ['', 'malformed XML'];
        yield 'malformed' => ['<CrossDomainAcknowledgementAndResponse><ExchangedDocument>', 'malformed XML'];
        yield 'unknown status' => [self::message(references: self::reference('F1', '299')), 'unknown ProcessConditionCode "299" for "F1"'];
        yield 'non-numeric status' => [self::message(references: self::reference('F1', 'OK')), 'unknown ProcessConditionCode "OK"'];
        yield 'missing status' => [self::message(references: '<ReferenceReferencedDocument><IssuerAssignedID>F1</IssuerAssignedID><StatusCode>45</StatusCode></ReferenceReferencedDocument>'), 'missing ProcessConditionCode for "F1"'];
        yield 'missing invoice number' => [self::message(references: '<ReferenceReferencedDocument><ProcessConditionCode>200</ProcessConditionCode></ReferenceReferencedDocument>'), 'missing ReferenceReferencedDocument/IssuerAssignedID'];
        yield 'no reference' => [self::message(references: ''), 'no AcknowledgementDocument/ReferenceReferencedDocument'];
        yield 'missing header date' => [self::message(header: ''), 'invalid ExchangedDocument/IssueDateTime ""'];
        yield 'invalid header date' => [self::message(header: '<DateTimeString format="204">20261340250000</DateTimeString>'), 'invalid ExchangedDocument/IssueDateTime "20261340250000" (format "204")'];
        yield 'unsupported date format' => [self::message(header: '<DateTimeString format="205">202610200830+0100</DateTimeString>'), '(format "205")'];
        yield 'invalid invoice date' => [self::message(references: self::reference('F1', '200', '<FormattedIssueDateTime><DateTimeString format="102">2026-10-20</DateTimeString></FormattedIssueDateTime>')), 'invalid FormattedIssueDateTime'];
    }

    private static function sample(string $relativePath): string
    {
        $content = file_get_contents(AfnorSamplesXsdTest::AFNOR . '/' . $relativePath);
        self::assertIsString($content);

        return $content;
    }

    /**
     * A minimal CDAR without any namespace prefix, to prove the parser does
     * not depend on the AFNOR prefixes.
     */
    private static function message(
        string $header = '<DateTimeString format="204">20261020083000</DateTimeString>',
        ?string $references = null,
        string $status = '',
    ): string {
        $references ??= self::reference('F2026-000042', '200', '' === $status ? '' : '<SpecifiedDocumentStatus>' . $status . '</SpecifiedDocumentStatus>');

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <CrossDomainAcknowledgementAndResponse xmlns="urn:un:unece:uncefact:data:standard:CrossDomainAcknowledgementAndResponse:100">
              <ExchangedDocument><IssueDateTime>{$header}</IssueDateTime></ExchangedDocument>
              <AcknowledgementDocument>{$references}</AcknowledgementDocument>
            </CrossDomainAcknowledgementAndResponse>
            XML;
    }

    private static function reference(string $number, string $status, string $extra = ''): string
    {
        return '<ReferenceReferencedDocument><IssuerAssignedID>' . $number . '</IssuerAssignedID>'
            . '<ProcessConditionCode>' . $status . '</ProcessConditionCode>' . $extra . '</ReferenceReferencedDocument>';
    }

    /**
     * @return list<string>
     */
    private static function cdvSamples(): array
    {
        $files    = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(AfnorSamplesXsdTest::AFNOR, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);
            if (str_contains($file->getFilename(), '-CDV-') && str_ends_with($file->getFilename(), '.xml') && !str_contains($file->getFilename(), ' - Copia')) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
