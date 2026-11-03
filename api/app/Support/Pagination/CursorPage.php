<?php

namespace App\Support\Pagination;

use Illuminate\Support\Collection;

/**
 * @template T
 */
class CursorPage
{
    /**
     * @param  Collection<int, T>  $items
     */
    public function __construct(
        public readonly Collection $items,
        public readonly ?string $nextCursor,
        public readonly int $limit,
        public readonly int $total,
    ) {}
}
