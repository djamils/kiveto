<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalIdsMatching;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Identifiers of every animal of a clinic matching a free-text term and/or a
 * species.
 *
 * Unlike SearchAnimals this returns bare identifiers and is not paginated: it
 * exists so another context can narrow its own paginated query by animal
 * without reaching into the Animal tables. The free text is matched against the
 * denormalised search entry — animal name, owner name and breed — so a caller
 * can offer a single "patient or owner" search box.
 */
final readonly class ListAnimalIdsMatching implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public ?string $searchTerm = null,
        public ?string $species = null,
    ) {
    }
}
