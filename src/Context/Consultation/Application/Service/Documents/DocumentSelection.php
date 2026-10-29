<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Service\Documents;

use App\Context\Consultation\Application\Port\ClientContact;
use App\Context\Consultation\Application\Port\ConsultationInvoice;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;

/**
 * The documents of a closed consultation the user ticked, checked against
 * it, and the owner they go to.
 */
final readonly class DocumentSelection
{
    /**
     * @param array<string, string> $prescriptions prescription id => number, in the order of the consultation
     */
    public function __construct(
        public ConsultationDetailsDTO $consultation,
        public string $animalName,
        public ClientContact $owner,
        public bool $report,
        public array $prescriptions,
        public ?ConsultationInvoice $invoice,
    ) {
    }

    public function count(): int
    {
        return ($this->report ? 1 : 0) + \count($this->prescriptions) + (null !== $this->invoice ? 1 : 0);
    }
}
