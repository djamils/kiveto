<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class MotifTag
{
    private function __construct(
        private string $id,
        private string $label,
    ) {
    }

    public static function create(string $label): self
    {
        $label = trim($label);

        if ('' === $label) {
            throw new \InvalidArgumentException('Motif label cannot be empty');
        }

        if (mb_strlen($label) > 120) {
            throw new \InvalidArgumentException('Motif label cannot exceed 120 characters');
        }

        return new self(Uuid::v7()->toString(), $label);
    }

    public static function reconstitute(string $id, string $label): self
    {
        return new self($id, $label);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
