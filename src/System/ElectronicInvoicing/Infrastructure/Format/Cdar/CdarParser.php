<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Format\Cdar;

use App\System\ElectronicInvoicing\Application\Exception\UnparsableLifecycleMessageException;
use App\System\ElectronicInvoicing\Application\Port\LifecycleMessageParserInterface;
use App\System\ElectronicInvoicing\Application\Port\ParsedLifecycleMessage;
use App\System\ElectronicInvoicing\Domain\Transmission\LifecycleStatusCode;

/**
 * Tolerant reader of CDAR lifecycle messages (« CDV », XP Z12-012).
 *
 * No CDAR XSD is available, so the document is not schema-validated: the
 * parser navigates on local names (any prefix / namespace) and only requires
 * what it needs. The status is the French lifecycle code carried by
 * `ProcessConditionCode`; the UNTDID 1373 `StatusCode` is ignored.
 *
 * Date-times without an explicit offset (format 204 `YYYYMMDDHHMMSS`, format
 * 102 `YYYYMMDD`) are interpreted in Europe/Paris, the French platforms' local
 * time; an ISO 8601 value with an offset keeps that offset.
 */
final readonly class CdarParser implements LifecycleMessageParserInterface
{
    private const string LOCAL_TIMEZONE = 'Europe/Paris';

    public function parse(string $content): array
    {
        $xpath = new \DOMXPath($this->load($content));

        $issuedAt = $this->dateTime(
            $this->first($xpath, "/*/*[local-name()='ExchangedDocument']/*[local-name()='IssueDateTime']/*"),
            'ExchangedDocument/IssueDateTime',
        );

        $references = $xpath->query("/*/*[local-name()='AcknowledgementDocument']/*[local-name()='ReferenceReferencedDocument']");
        if (false === $references || 0 === $references->length) {
            throw new UnparsableLifecycleMessageException('no AcknowledgementDocument/ReferenceReferencedDocument.');
        }

        $messages = [];
        foreach ($references as $reference) {
            \assert($reference instanceof \DOMElement);
            $messages[] = $this->parseReference($xpath, $reference, $issuedAt);
        }

        return $messages;
    }

    private function load(string $content): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument();
            if ('' === trim($content) || !$document->loadXML($content, \LIBXML_NONET)) {
                throw new UnparsableLifecycleMessageException('malformed XML.');
            }

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function parseReference(\DOMXPath $xpath, \DOMNode $reference, \DateTimeImmutable $issuedAt): ParsedLifecycleMessage
    {
        $invoiceNumber = $this->text($xpath, "*[local-name()='IssuerAssignedID']", $reference)
            ?? throw new UnparsableLifecycleMessageException('missing ReferenceReferencedDocument/IssuerAssignedID.');

        $rawStatus = $this->text($xpath, "*[local-name()='ProcessConditionCode']", $reference)
            ?? throw new UnparsableLifecycleMessageException(\sprintf('missing ProcessConditionCode for "%s".', $invoiceNumber));
        $status = 1 === preg_match('/^\d+$/', $rawStatus) ? LifecycleStatusCode::tryFrom((int) $rawStatus) : null;
        if (null === $status) {
            throw new UnparsableLifecycleMessageException(\sprintf('unknown ProcessConditionCode "%s" for "%s".', $rawStatus, $invoiceNumber));
        }

        $issueDateNode = $this->first($xpath, "*[local-name()='FormattedIssueDateTime']/*", $reference);

        $statusPath = "*[local-name()='SpecifiedDocumentStatus']";

        return new ParsedLifecycleMessage(
            statusCode: $status->value,
            reasonCode: $this->text($xpath, $statusPath . "/*[local-name()='ReasonCode']", $reference),
            reasonText: $this->text($xpath, $statusPath . "/*[local-name()='Reason']", $reference)
                ?? $this->text($xpath, $statusPath . "/*[local-name()='IncludedNote']/*[local-name()='Content']", $reference),
            invoiceNumber: $invoiceNumber,
            invoiceIssueDate: null === $issueDateNode ? null : $this->dateTime($issueDateNode, 'FormattedIssueDateTime'),
            typeCode: $this->text($xpath, "*[local-name()='TypeCode']", $reference),
            sellerSiren: $this->text($xpath, "*[local-name()='IssuerTradeParty']/*[local-name()='GlobalID'][@schemeID='0002']", $reference),
            issuedAt: $issuedAt,
        );
    }

    /**
     * The trimmed text of the first non-blank node matching $expression, or null.
     */
    private function text(\DOMXPath $xpath, string $expression, ?\DOMNode $context = null): ?string
    {
        foreach ($xpath->query($expression, $context) ?: [] as $node) {
            $value = self::trim((string) $node->nodeValue);
            if ('' !== $value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * trim() plus the no-break spaces found in real messages.
     */
    private static function trim(string $value): string
    {
        return (string) preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $value);
    }

    private function first(\DOMXPath $xpath, string $expression, ?\DOMNode $context = null): ?\DOMElement
    {
        $node = ($xpath->query($expression, $context) ?: null)?->item(0);

        return $node instanceof \DOMElement ? $node : null;
    }

    private function dateTime(?\DOMElement $node, string $what): \DateTimeImmutable
    {
        $value  = null === $node ? '' : self::trim($node->textContent);
        $format = $node?->getAttribute('format') ?? '';
        $zone   = new \DateTimeZone(self::LOCAL_TIMEZONE);

        $parsed = match ($format) {
            '204' => \DateTimeImmutable::createFromFormat('!YmdHis', $value, $zone),
            '102' => \DateTimeImmutable::createFromFormat('!Ymd', $value, $zone),
            ''    => \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value, $zone)
                ?: \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', $value, $zone),
            default => false,
        };

        $errors = \DateTimeImmutable::getLastErrors();
        if (false === $parsed || (false !== $errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new UnparsableLifecycleMessageException(\sprintf('invalid %s "%s" (format "%s").', $what, $value, $format));
        }

        return $parsed;
    }
}
