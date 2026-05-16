<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

enum FoodProductionPurpose: string
{
    case MUSCLE_SKIN       = 'MUSCLE_SKIN';
    case LIVER             = 'LIVER';
    case KIDNEY            = 'KIDNEY';
    case FAT               = 'FAT';
    case FAT_WITH_SKIN     = 'FAT_WITH_SKIN';
    case EGGS              = 'EGGS';
    case HONEY             = 'HONEY';
    case MILK              = 'MILK';
    case ALL_FOOD_PRODUCTS = 'ALL_FOOD_PRODUCTS';
    case NOT_APPLICABLE    = 'NOT_APPLICABLE';
}
