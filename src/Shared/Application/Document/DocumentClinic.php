<?php

declare(strict_types=1);

namespace App\Shared\Application\Document;

/**
 * The clinic as its documents print it: name, colour, logo and letterhead.
 * Each bounded context reads it from the Clinic context through its own port.
 */
final readonly class DocumentClinic
{
    public function __construct(
        public string $name,
        public ?DocumentLetterhead $letterhead = null,
        public ?string $brandColor = null,
        /** The stored file of the logo, inlined by `clinic_logo_data_uri()`. */
        public ?string $logoKey = null,
    ) {
    }
}
