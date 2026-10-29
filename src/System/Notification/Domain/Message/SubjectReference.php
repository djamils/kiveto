<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Message;

/**
 * The business object a message is about (an appointment, an invoice, a
 * consultation, a user…), as the requester names it.
 */
final readonly class SubjectReference
{
    private function __construct(
        public string $type,
        public string $id,
    ) {
    }

    public static function of(string $type, string $id): self
    {
        $type = mb_trim($type);
        $id   = mb_trim($id);

        if ('' === $type || '' === $id || mb_strlen($type) > 40 || mb_strlen($id) > 64) {
            throw new \InvalidArgumentException(\sprintf('Invalid subject reference "%s:%s".', $type, $id));
        }

        return new self($type, $id);
    }
}
