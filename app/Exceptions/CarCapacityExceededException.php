<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class CarCapacityExceededException extends RuntimeException
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $carId,
        public readonly string $dimension,
        public readonly string $capacity,
        public readonly string $projected,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Car %d exceeds %s capacity (capacity %s, projected %s).',
                $carId,
                $dimension,
                $capacity,
                $projected
            ),
            0,
            $previous
        );
    }
}
