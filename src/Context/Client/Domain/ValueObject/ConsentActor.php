<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

/**
 * Who changed a consent: a Portal user, a staff member or the system, with
 * the name shown in the history at the time of the change.
 */
final readonly class ConsentActor
{
    public const int LABEL_MAX_LENGTH = 255;

    public string $label;

    public function __construct(
        public ConsentActorType $type,
        public ?string $id,
        string $label,
    ) {
        $label = mb_trim($label);

        if ('' === $label || mb_strlen($label) > self::LABEL_MAX_LENGTH) {
            throw new \InvalidArgumentException('A consent author needs a label of 1 to 255 characters.');
        }

        if (ConsentActorType::SYSTEM === $type && null !== $id) {
            throw new \InvalidArgumentException('The system acts without an id.');
        }

        if (null !== $id && 1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            throw new \InvalidArgumentException(\sprintf('Consent author id "%s" is not a UUID.', $id));
        }

        $this->label = $label;
    }
}
