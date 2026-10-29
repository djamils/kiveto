<?php

declare(strict_types=1);

namespace App\Shared\Application\Document;

/**
 * The identity block of a clinic as the document templates read it: flat
 * strings, already formatted for paper.
 */
final readonly class DocumentLetterhead
{
    /**
     * @param list<string> $addressLines
     */
    public function __construct(
        public ?string $legalName,
        public array $addressLines,
        /** Where the document is drawn up: "Fait à …". */
        public ?string $city,
        public ?string $phone,
        public ?string $email,
        public ?string $website,
        /** "812 456 903 00028". */
        public ?string $formattedSiret,
        public ?string $vatNumber,
        public ?string $ordinalNumber,
        public ?string $ordinalCouncil,
        /** "SELARL au capital de 30 000 €, RCS Paris 812 456 903, code APE 7500Z". */
        public ?string $legalSentence,
        /** "Assurance responsabilité civile professionnelle : MACSF, contrat n° …". */
        public ?string $liabilitySentence,
    ) {
    }
}
