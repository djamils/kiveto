<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Dto;

use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\ExternalReference;

final readonly class SendOrderResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?ExternalReference $externalReference,
        public readonly \DateTimeImmutable $sentAt,
        public readonly ?string $errorMessage,
        public readonly ?string $trackingUrl,
    ) {
    }

    public static function success(ExternalReference $ref, \DateTimeImmutable $sentAt, ?string $trackingUrl = null): self
    {
        return new self(
            success: true,
            externalReference: $ref,
            sentAt: $sentAt,
            errorMessage: null,
            trackingUrl: $trackingUrl,
        );
    }

    public static function failure(string $errorMessage, \DateTimeImmutable $sentAt): self
    {
        return new self(
            success: false,
            externalReference: null,
            sentAt: $sentAt,
            errorMessage: $errorMessage,
            trackingUrl: null,
        );
    }
}
