<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France;

use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto\AnmvCompositionDto;
use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto\AnmvMedicinalProductDto;
use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto\AnmvPresentationDto;
use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto\AnmvVoieAdministrationDto;

/** Streaming XMLReader parser — never loads the full 53 MB ANMV XML into memory. */
final class AnmvXmlParser
{
    /**
     * Yields one AnmvMedicinalProductDto per <medicinal-product> element.
     *
     * @return \Generator<AnmvMedicinalProductDto>
     */
    public function parse(string $filePath, AnmvDictionaryCache $dico): \Generator
    {
        $reader = new \XMLReader();
        $reader->open($filePath);

        while ($reader->read()) {
            if (\XMLReader::ELEMENT !== $reader->nodeType || 'medicinal-product' !== $reader->localName) {
                continue;
            }

            $node = $reader->expand();

            if (!$node instanceof \DOMElement) {
                continue;
            }

            $dto = $this->parseProduct($node, $dico);

            if (null !== $dto) {
                yield $dto;
            }
        }

        $reader->close();
    }

    private function parseProduct(\DOMElement $node, AnmvDictionaryCache $dico): ?AnmvMedicinalProductDto
    {
        $authorityIdentifier = $node->getAttribute('num-amm') ?: null;
        $commercialName      = $node->getAttribute('name') ?: null;
        $holderLaboratory    = $node->getAttribute('holder') ?: null;
        $authorizationDate   = $node->getAttribute('date-amm') ?: (new \DateTimeImmutable())->format('Y-m-d');
        $statusCode          = (int) ($node->getAttribute('status-code') ?: 0);
        $natureCode          = (int) ($node->getAttribute('nature-code') ?: 0);
        $pharmaceuticalForm  = $node->getAttribute('pharmaceutical-form') ?: '';
        $atcVetCode          = $node->getAttribute('atc-vet-code') ?: null;
        $permanentIdentifier = $node->getAttribute('permanent-id') ?: null;

        if (null === $authorityIdentifier || null === $commercialName || null === $holderLaboratory) {
            return null;
        }

        $presentations       = $this->parsePresentations($node, $dico);
        $compositions        = $this->parseCompositions($node, $dico);
        $voiesAdministration = $this->parseVoies($node, $dico);

        return new AnmvMedicinalProductDto(
            authorityIdentifier: $authorityIdentifier,
            commercialName: $commercialName,
            holderLaboratory: $holderLaboratory,
            statusCode: $statusCode,
            authorizationDate: $authorizationDate,
            natureCode: $natureCode,
            pharmaceuticalForm: $pharmaceuticalForm,
            atcVetCode: '' !== ($atcVetCode ?? '') ? $atcVetCode : null,
            permanentIdentifier: '' !== ($permanentIdentifier ?? '') ? $permanentIdentifier : null,
            presentations: $presentations,
            compositions: $compositions,
            voiesAdministration: $voiesAdministration,
        );
    }

    /**
     * @return AnmvPresentationDto[]
     */
    private function parsePresentations(\DOMElement $node, AnmvDictionaryCache $dico): array
    {
        $presentations = [];

        foreach ($node->getElementsByTagName('presentation') as $presentationNode) {
            $prescriptionCode = (int) ($presentationNode->getAttribute('pres-code') ?: 0);

            $presentations[] = new AnmvPresentationDto(
                description: $presentationNode->getAttribute('description') ?: '',
                unitCount: $presentationNode->getAttribute('unit-count') ?: null,
                packaging: $presentationNode->getAttribute('packaging') ?: null,
                gtin: $presentationNode->getAttribute('gtin') ?: null,
                euPackIdentifier: $presentationNode->getAttribute('eu-pack-id') ?: null,
                prescriptionCode: $prescriptionCode,
            );
        }

        return $presentations;
    }

    /**
     * @return AnmvCompositionDto[]
     */
    private function parseCompositions(\DOMElement $node, AnmvDictionaryCache $dico): array
    {
        $compositions = [];

        foreach ($node->getElementsByTagName('composition') as $compositionNode) {
            $substanceId    = (int) ($compositionNode->getAttribute('sub-code') ?: 0);
            $substanceLabel = $substanceId > 0 ? $dico->getActiveSubstanceLabel($substanceId) : ($compositionNode->getAttribute('sub-name') ?: '');

            $compositions[] = new AnmvCompositionDto(
                activeSubstanceLabel: $substanceLabel,
                quantityValue: $compositionNode->getAttribute('qty') ?: null,
                quantityUnitLabel: $compositionNode->getAttribute('unit-label') ?: null,
                quantityUnitCode: $compositionNode->getAttribute('unit-code') ?: null,
                isExcipient: '1' === $compositionNode->getAttribute('is-excipient'),
            );
        }

        return $compositions;
    }

    /**
     * @return AnmvVoieAdministrationDto[]
     */
    private function parseVoies(\DOMElement $node, AnmvDictionaryCache $dico): array
    {
        $voies = [];

        foreach ($node->getElementsByTagName('voie-administration') as $voieNode) {
            $routeCode   = (int) ($voieNode->getAttribute('route-code') ?: 0);
            $speciesCode = (int) ($voieNode->getAttribute('species-code') ?: 0);
            $fpCode      = $voieNode->getAttribute('fp-code') ?: null;
            $wpQty       = $voieNode->getAttribute('wp-qty') ?: null;
            $wpUnitCode  = $voieNode->getAttribute('wp-unit-code') ?: null;

            $voies[] = new AnmvVoieAdministrationDto(
                routeCode: $routeCode,
                speciesCode: $speciesCode,
                foodProductionCode: $fpCode,
                withdrawalPeriodQuantity: $wpQty,
                withdrawalPeriodUnitCode: null !== $wpUnitCode ? (int) $wpUnitCode : null,
                jurisdictionalNote: $voieNode->getAttribute('note') ?: null,
            );
        }

        return $voies;
    }
}
