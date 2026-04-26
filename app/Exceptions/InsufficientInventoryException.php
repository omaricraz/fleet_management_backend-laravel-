<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

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
