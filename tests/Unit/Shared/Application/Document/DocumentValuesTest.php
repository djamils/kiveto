<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Document;

use App\Shared\Application\Document\DocumentClinic;
use App\Shared\Application\Document\DocumentFooter;
use App\Shared\Application\Document\DocumentLetterhead;
use App\Shared\Application\Document\RenderedDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentClinic::class)]
#[CoversClass(DocumentFooter::class)]
#[CoversClass(DocumentLetterhead::class)]
#[CoversClass(RenderedDocument::class)]
final class DocumentValuesTest extends TestCase
{
    public function testTheFooterCarriesTheClinicItsLetterheadTheReferenceAndTheSubject(): void
    {
        $letterhead = new DocumentLetterhead(
            legalName: 'SELARL Montmartre',
            addressLines: ['18 rue des Abbesses', '75018 Paris'],
            city: 'Paris',
            phone: '+33142641800',
            email: 'contact@montmartre.vet',
            website: null,
            formattedSiret: '812 456 903 00028',
            vatNumber: 'FR20812456903',
            ordinalNumber: '21284',
            ordinalCouncil: 'Île-de-France',
            legalSentence: 'SELARL',
            liabilitySentence: null,
        );
        $footer = new DocumentFooter(new DocumentClinic('Clinique Montmartre', $letterhead, '#4f86c6', 'logo.png'), 'Facture · FA-2026-0814', 'Sophie Garnier');

        self::assertSame('Clinique Montmartre', $footer->clinic?->name);
        self::assertSame('Paris', $footer->clinic->letterhead?->city);
        self::assertSame('#4f86c6', $footer->clinic->brandColor);
        self::assertSame('logo.png', $footer->clinic->logoKey);
        self::assertSame('Facture · FA-2026-0814', $footer->reference);
        self::assertSame('Sophie Garnier', $footer->subject);
    }

    public function testARenderedDocumentIsAFileNameAndItsContent(): void
    {
        $document = new RenderedDocument('FA-2026-0814.pdf', '%PDF');

        self::assertSame('FA-2026-0814.pdf', $document->filename);
        self::assertSame('%PDF', $document->content);
    }
}
