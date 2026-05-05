<?php

namespace App\Traits;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use function request;

trait SqlTrait
{
    /**
     * Execute a raw SQL query and return paginated results.
     *
     * The method calculates total items by wrapping the provided statement
     * in a counting subquery, then applies LIMIT/OFFSET for the current page.
     *
     * @param  string  $statement  Base SQL statement (without LIMIT/OFFSET).
     * @param  array<int|string, mixed>  $bindings  Parameter bindings for the SQL statement.
     * @param  int|null  $perPage  Items per page. Defaults to 20 when null.
     */
    public function sqlPaginated(string $statement, array $bindings = [], ?int $perPage = null): LengthAwarePaginator
    {
        $perPage = $this->getPerPage($perPage);

        $page = $this->getPageNum();

        $offset = ($page - 1) * $perPage;

        $itemsCount = DB::selectOne("SELECT COUNT(*) as count FROM ($statement) as data_tbl", $bindings)->count ?? 0;

        $items = DB::select($statement . " LIMIT $perPage OFFSET $offset", $bindings);

        return $this->getPaginatedData($items, $itemsCount, $perPage, $page);
    }

    /**
     * Resolve the effective page size.
     *
     * @param  int|null  $perPage  Requested page size.
     * @return int Page size, defaulting to 20.
     */
    private function getPerPage(?int $perPage): int
    {
        return $perPage ?? 20;
    }

    /**
     * Read the current page number from the query string.
     *
     * Uses `page` from the request query, with validation that enforces
     * a minimum value of 1 and falls back to 1 when invalid/missing.
     *
     * @return int Validated page number.
     */
    private function getPageNum(): int
    {
        return (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'default' => 1,
            ],
        ]);
    }

    /**
     * Build a Laravel paginator instance for the fetched records.
     *
     * The request `per_page` query value can override the provided per-page value.
     *
     * @param  array<int, mixed>  $items  Records for the current page.
     * @param  int  $itemsCount  Total matching records count.
     * @param  int  $perPage  Items per page.
     * @param  int  $page  Current page number.
     */
    private function getPaginatedData(array $items, int $itemsCount, int $perPage, int $page): LengthAwarePaginator
    {
        $perPage = min(request()->input('per_page', $perPage), 200);

        return new LengthAwarePaginator($items, $itemsCount, $perPage, $page, ['path' => request()->url(), 'query' => request()->query()]);
    }
}
