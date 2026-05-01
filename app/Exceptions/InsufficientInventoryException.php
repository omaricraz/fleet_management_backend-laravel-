<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

//this is for when there is not enough inventory to complete the operation, it throws an exception eg. "not enough inventory to load"
final class InsufficientInventoryException extends RuntimeException
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $carId,
        public readonly int $productId,
        public readonly string $available,
        public readonly string $requested,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Insufficient inventory for product %d on car %d (available %s, requested %s).',
                $productId,
                $carId,
                $available,
                $requested
            ),
            0,
            $previous
        );
    }
}
