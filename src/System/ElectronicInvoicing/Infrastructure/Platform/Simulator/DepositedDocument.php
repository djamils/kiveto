<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

/**
 * What the simulated platform reads from a deposited CII file to address its
 * lifecycle messages: document number (BT-1), type code (BT-3), issue date
 * (BT-2), seller (BT-30) and buyer (BT-47) SIRENs. Anything missing or
 * unreadable stays null.
 */
final readonly class DepositedDocument
{
    private function __construct(
        public ?string $number,
        public ?string $typeCode,
        public ?\DateTimeImmutable $issueDate,
        public ?string $sellerSiren,
        public ?string $buyerSiren,
    ) {
    }

    public static function read(string $content): self
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument();
            if ('' === trim($content) || !$document->loadXML($content, \LIBXML_NONET)) {
                return new self(null, null, null, null, null);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath   = new \DOMXPath($document);
        $header  = "/*/*[local-name()='ExchangedDocument']";
        $parties = "/*/*[local-name()='SupplyChainTradeTransaction']/*[local-name()='ApplicableHeaderTradeAgreement']";
        $siren   = "/*[local-name()='SpecifiedLegalOrganization']/*[local-name()='ID'][@schemeID='0002']";
        $date    = self::text($xpath, $header . "/*[local-name()='IssueDateTime']/*");

        return new self(
            number: self::text($xpath, $header . "/*[local-name()='ID']"),
            typeCode: self::text($xpath, $header . "/*[local-name()='TypeCode']"),
            issueDate: null === $date ? null : (\DateTimeImmutable::createFromFormat('!Ymd', $date) ?: null),
            sellerSiren: self::text($xpath, $parties . "/*[local-name()='SellerTradeParty']" . $siren),
            buyerSiren: self::text($xpath, $parties . "/*[local-name()='BuyerTradeParty']" . $siren),
        );
    }

    private static function text(\DOMXPath $xpath, string $expression): ?string
    {
        $value = $xpath->evaluate('string(' . $expression . ')');
        $value = \is_string($value) ? trim($value) : '';

        return '' === $value ? null : $value;
    }
}
