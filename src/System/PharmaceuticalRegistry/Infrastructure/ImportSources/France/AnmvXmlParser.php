<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France;

use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto\AnmvCompositionDto;
use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto\AnmvMedicinalProductDto;
use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto\AnmvPresentationDto;
use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\Dto\AnmvVoieAdministrationDto;

/** Streaming XMLReader parser — never loads the full ANMV XML into memory. */
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
        $authorityIdentifier = $this->textOf($node, 'num-amm');

        if ('' === $authorityIdentifier) {
            return null;
        }

        $commercialName = $this->textOf($node, 'nom');

        $holderCode            = (int) $this->textOf($node, 'term-tit');
        $holderLaboratoryLabel = $dico->getLabel('term-tit', $holderCode);

        $statusCode        = (int) $this->textOf($node, 'term-stat-auto');
        $authorizationDate = $this->textOf($node, 'date-amm');
        $natureCode        = (int) $this->textOf($node, 'term-nat');

        $fpCode                  = (int) $this->textOf($node, 'term-fp');
        $pharmaceuticalFormLabel = $dico->hasLabel('term-fp', $fpCode)
            ? $dico->getLabel('term-fp', $fpCode)
            : '';

        $atcVetCode = $this->textOfNested($node, 'atcvet-code', 'code-atcvet');
        $atcVetCode = '' !== ($atcVetCode ?? '') ? $atcVetCode : null;

        $permId              = $this->textOf($node, 'perm-id');
        $permanentIdentifier = ('' !== $permId && \strlen($permId) >= 12) ? $permId : null;

        $presentations       = $this->parsePresentations($node);
        $compositions        = $this->parseCompositions($node, $dico);
        $voiesAdministration = $this->parseVoies($node);

        return new AnmvMedicinalProductDto(
            authorityIdentifier: $authorityIdentifier,
            commercialName: $commercialName,
            holderLaboratoryLabel: $holderLaboratoryLabel,
            statusCode: $statusCode,
            authorizationDate: $authorizationDate,
            natureCode: $natureCode,
            pharmaceuticalFormLabel: $pharmaceuticalFormLabel,
            atcVetCode: $atcVetCode,
            permanentIdentifier: $permanentIdentifier,
            presentations: $presentations,
            compositions: $compositions,
            voiesAdministration: $voiesAdministration,
        );
    }

    /** Returns the text content of the first direct child element matching $tag, or '' if not found. */
    private function textOf(\DOMElement $el, string $tag): string
    {
        $children = $el->childNodes;

        for ($i = 0; $i < $children->length; ++$i) {
            $child = $children->item($i);

            if ($child instanceof \DOMElement && $child->localName === $tag) {
                return trim($child->textContent);
            }
        }

        return '';
    }

    /**
     * Returns the text content of the first child of $outerTag matching $innerTag,
     * or null if either element is absent.
     */
    private function textOfNested(\DOMElement $el, string $outerTag, string $innerTag): ?string
    {
        $children = $el->childNodes;

        for ($i = 0; $i < $children->length; ++$i) {
            $child = $children->item($i);

            if (!($child instanceof \DOMElement) || $child->localName !== $outerTag) {
                continue;
            }

            $inner = $child->childNodes;

            for ($j = 0; $j < $inner->length; ++$j) {
                $item = $inner->item($j);

                if ($item instanceof \DOMElement && $item->localName === $innerTag) {
                    $text = trim($item->textContent);

                    return '' !== $text ? $text : null;
                }
            }

            return null;
        }

        return null;
    }

    /**
     * @return AnmvPresentationDto[]
     */
    private function parsePresentations(\DOMElement $node): array
    {
        $presentations = [];

        // Build a lib-mod → gtin/pack-id index from <mdv-codes-gtin>
        $gtinIndex = $this->buildGtinIndex($node);

        $mdv = $this->findChild($node, 'modele-destine-vente');

        if (null === $mdv) {
            return $presentations;
        }

        foreach ($mdv->childNodes as $child) {
            if (!($child instanceof \DOMElement) || 'mod-vte' !== $child->localName) {
                continue;
            }

            $description = $this->textOf($child, 'lib-mod');
            $nbUnit      = $this->textOf($child, 'nb-unit');
            $libCondp    = $this->textOf($child, 'lib-condp');
            $termCd      = (int) $this->textOf($child, 'term-cd');

            $gtin   = $gtinIndex[$description]['gtin'] ?? null;
            $packId = $gtinIndex[$description]['pack-id'] ?? null;

            $presentations[] = new AnmvPresentationDto(
                description: $description,
                unitCount: '' !== $nbUnit ? $nbUnit : null,
                packaging: '' !== $libCondp ? $libCondp : null,
                gtin: $gtin,
                euPackIdentifier: $packId,
                prescriptionCode: $termCd,
            );
        }

        return $presentations;
    }

    /**
     * Builds a description → ['gtin' => ?string, 'pack-id' => ?string] index
     * from the <mdv-codes-gtin> section.
     *
     * @return array<string, array{gtin: string|null, 'pack-id': string|null}>
     */
    private function buildGtinIndex(\DOMElement $node): array
    {
        $index = [];

        $gtinSection = $this->findChild($node, 'mdv-codes-gtin');

        if (null === $gtinSection) {
            return $index;
        }

        foreach ($gtinSection->childNodes as $child) {
            if (!($child instanceof \DOMElement) || 'mod-vte' !== $child->localName) {
                continue;
            }

            $libMod = $this->textOf($child, 'lib-mod');
            $packId = $this->textOf($child, 'pack-id');
            $gtin   = $this->textOf($child, 'code-gtin');

            $index[$libMod] = [
                'gtin'    => '' !== $gtin ? $gtin : null,
                'pack-id' => '' !== $packId ? $packId : null,
            ];
        }

        return $index;
    }

    /**
     * @return AnmvCompositionDto[]
     */
    private function parseCompositions(\DOMElement $node, AnmvDictionaryCache $dico): array
    {
        $compositions = [];

        $composition = $this->findChild($node, 'composition');

        if (null === $composition) {
            return $compositions;
        }

        foreach ($composition->childNodes as $child) {
            if (!($child instanceof \DOMElement) || 'compo' !== $child->localName) {
                continue;
            }

            $sa = $this->findChild($child, 'sa');

            if (null === $sa) {
                continue;
            }

            $saCode    = (int) $this->textOf($sa, 'term-sa');
            $quantity  = $this->textOf($sa, 'quantite');
            $uniteCode = $this->textOf($sa, 'term-unite');

            $substanceLabel = $dico->hasLabel('term-sa', $saCode)
                ? $dico->getLabel('term-sa', $saCode)
                : (string) $saCode;

            $unitLabel = '' !== $uniteCode && $dico->hasLabel('term-unite', (int) $uniteCode)
                ? $dico->getLabel('term-unite', (int) $uniteCode)
                : null;

            $compositions[] = new AnmvCompositionDto(
                activeSubstanceLabel: $substanceLabel,
                quantityValue: '' !== $quantity ? $quantity : null,
                quantityUnitLabel: $unitLabel,
                quantityUnitCode: '' !== $uniteCode ? $uniteCode : null,
                isExcipient: false,
            );
        }

        return $compositions;
    }

    /**
     * @return AnmvVoieAdministrationDto[]
     */
    private function parseVoies(\DOMElement $node): array
    {
        $voies = [];

        $voiesSection = $this->findChild($node, 'voie-administration');

        if (null === $voiesSection) {
            return $voies;
        }

        foreach ($voiesSection->childNodes as $child) {
            if (!($child instanceof \DOMElement) || 'voie-admin' !== $child->localName) {
                continue;
            }

            $routeCode   = (int) $this->textOf($child, 'term-va');
            $speciesCode = (int) $this->textOf($child, 'term-esp');
            $denrRaw     = $this->textOf($child, 'term-denr');

            $foodProductionCode = '' !== $denrRaw ? (int) $denrRaw : null;

            // Code 10 = NOT_APPLICABLE — treat as null (no food production restriction)
            if (10 === $foodProductionCode) {
                $foodProductionCode = null;
            }

            $voies[] = new AnmvVoieAdministrationDto(
                routeCode: $routeCode,
                speciesCode: $speciesCode,
                foodProductionCode: $foodProductionCode,
                withdrawalPeriodQuantity: null,
                withdrawalPeriodUnitCode: null,
                jurisdictionalNote: null,
            );
        }

        return $voies;
    }

    /** Returns the first direct child DOMElement with the given local name, or null. */
    private function findChild(\DOMElement $el, string $tag): ?\DOMElement
    {
        foreach ($el->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $tag) {
                return $child;
            }
        }

        return null;
    }
}
