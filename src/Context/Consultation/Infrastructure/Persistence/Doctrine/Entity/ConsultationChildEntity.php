<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity;

/**
 * Child rows of the consultation aggregate, keyed by a binary UUID and linked
 * to their root through a loose `consultation_id` column (no ORM relation).
 *
 * Implementing this interface lets the repository synchronise any child
 * collection by id with a single generic routine.
 */
interface ConsultationChildEntity
{
    public function getId(): string;
}
