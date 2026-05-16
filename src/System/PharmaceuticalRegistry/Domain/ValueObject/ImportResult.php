<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

final class ImportResult
{
    private int $created;
    private int $updated;
    private int $withdrawn;
    private int $skipped;

    private function __construct(int $created, int $updated, int $withdrawn, int $skipped)
    {
        $this->created   = $created;
        $this->updated   = $updated;
        $this->withdrawn = $withdrawn;
        $this->skipped   = $skipped;
    }

    public static function of(int $created, int $updated, int $withdrawn, int $skipped): self
    {
        return new self($created, $updated, $withdrawn, $skipped);
    }

    public function created(): int
    {
        return $this->created;
    }

    public function updated(): int
    {
        return $this->updated;
    }

    public function withdrawn(): int
    {
        return $this->withdrawn;
    }

    public function skipped(): int
    {
        return $this->skipped;
    }
}
