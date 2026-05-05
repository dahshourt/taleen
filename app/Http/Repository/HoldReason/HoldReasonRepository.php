<?php

namespace App\Http\Repository\HoldReason;

use App\Contracts\HoldReason\HoldReasonRepositoryInterface;
use App\Models\HoldReason;
use Illuminate\Support\Collection;

class HoldReasonRepository implements HoldReasonRepositoryInterface
{
    public function getAll(): Collection
    {
        return HoldReason::all();
    }

    public function getAllActive(): Collection
    {
        return HoldReason::active()->get();
    }

    public function create($request): HoldReason
    {
        return HoldReason::create($request);
    }

    public function delete($id): bool
    {
        return HoldReason::destroy($id);
    }

    public function update($request, $id): bool
    {
        return HoldReason::where('id', $id)->update($request);
    }

    public function find($id): ?HoldReason
    {
        return HoldReason::find($id);
    }
}
