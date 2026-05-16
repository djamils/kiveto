<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

final class SummaryLink
{
    private string $url;

    private function __construct(string $url)
    {
        if (mb_strlen($url) > 512) {
            throw new \InvalidArgumentException('Summary link URL must not exceed 512 characters.');
        }

        if (1 !== preg_match('/^https?:\/\//', $url)) {
            throw new \InvalidArgumentException(
                \sprintf('Invalid summary link URL: "%s". Must start with http:// or https://.', $url),
            );
        }

        $this->url = $url;
    }

    public static function fromString(string $url): self
    {
        return new self($url);
    }

    public function toString(): string
    {
        return $this->url;
    }
}
