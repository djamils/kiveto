<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * The owner of the animal as the documents e-mail reaches them.
 */
final readonly class ClientContact
{
    /**
     * @param string $name      « Sophie Dubois » (the recipient's name)
     * @param string $addressee « Madame Dubois » (the greeting)
     */
    public function __construct(
        public string $clientId,
        public string $name,
        public string $addressee,
        public ?string $email,
        public bool $archived,
    ) {
    }

    /** « Envoyer par e-mail »: an e-mail on file, client not archived. */
    public function canReceiveDocuments(): bool
    {
        return !$this->archived && null !== $this->email;
    }
}
