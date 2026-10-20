<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Format;

/**
 * Validates an XML document against a local XSD, without network access.
 *
 * Malformed XML, schema violations and unreadable schemas all come back as
 * messages (never as an exception); an empty list means the document is
 * valid. The caller's libxml error-handling state is restored afterwards.
 */
final readonly class XsdValidator
{
    /**
     * @return list<string>
     */
    public function validate(string $xml, string $schemaPath): array
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $document = new \DOMDocument();
            if ('' === $xml || !$document->loadXML($xml, \LIBXML_NONET)) {
                return $this->collectErrors('The document is not well-formed XML.');
            }

            if (!$document->schemaValidate($schemaPath, \LIBXML_NONET)) {
                return $this->collectErrors('The document does not match the schema.');
            }

            return [];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * @return list<string>
     */
    private function collectErrors(string $fallback): array
    {
        $errors = [];
        foreach (libxml_get_errors() as $error) {
            $errors[] = \sprintf('line %d: %s', $error->line, trim($error->message));
        }

        return [] === $errors ? [$fallback] : $errors;
    }
}
