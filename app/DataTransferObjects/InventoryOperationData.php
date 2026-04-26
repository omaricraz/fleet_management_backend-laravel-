<?php

namespace App\DataTransferObjects;

use App\Support\InventoryMath;

final readonly class InventoryOperationData
{
    public function __construct(
        public int $productId,
        public string $quantity,
    ) {}

    /**
     * @param  array{product_id: int|string, quantity: string|int|float}  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            (int) $row['product_id'],
            InventoryMath::normalize($row['quantity']),
        );
    }

    /**
     * @param  list<array{product_id: int|string, quantity: string|int|float}>  $rows
     * @return list<self>
     */
    public static function manyFromArray(array $rows): array
    {
        return array_map(static fn (array $r) => self::fromArray($r), $rows);
    }
}
