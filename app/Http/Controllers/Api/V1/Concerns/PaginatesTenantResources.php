<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResource;
use Illuminate\Http\Request;

trait PaginatesTenantResources
{
    /**
     * @param  class-string<JsonResource>  $resourceClass
     * @return array{items: list<array<string, mixed>>, meta: array<string, int|null>}
     */
    protected function formatPaginated(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        return [
            'items' => collect($paginator->items())
                ->map(fn ($model) => (new $resourceClass($model))->toArray(request()))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $searchColumns
     * @param  list<string>  $sortable
     */
    protected function applyTenantListFilters(
        Builder $query,
        Request $request,
        array $searchColumns,
        array $sortable,
        string $defaultSort = 'id'
    ): LengthAwarePaginator {
        $perPage = (int) $request->query('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $sort = (string) $request->query('sort', $defaultSort);
        if (! in_array($sort, $sortable, true)) {
            $sort = $defaultSort;
        }

        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($searchColumns, $search): void {
                foreach ($searchColumns as $column) {
                    $q->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        $query->orderBy($sort, $direction);

        return $query->paginate($perPage)->withQueryString();
    }
}
