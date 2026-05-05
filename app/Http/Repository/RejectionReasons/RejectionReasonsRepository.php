<?php

namespace App\Http\Repository\RejectionReasons;

use App\Contracts\RejectionReasons\RejectionReasonsRepositoryInterface;
// declare Entities
use App\Models\RejectionReasons;

class RejectionReasonsRepository implements RejectionReasonsRepositoryInterface
{
    public function getAll()
    {
        return RejectionReasons::all();
    }

    public function paginateAll()
    {
        return RejectionReasons::latest()->paginate(10);
    }

    public function create($request)
    {
        return RejectionReasons::create($request);
    }

    public function delete($id)
    {
        return RejectionReasons::destroy($id);
    }

    public function update($request, $id)
    {
        return RejectionReasons::where('id', $id)->update($request);
    }

    public function find($id)
    {

        return RejectionReasons::find($id);
    }

    public function workflows($id)
    {

        return RejectionReasons::where('workflow_type_id', $id)->where('active', '1')->get();
    }

    public function updateactive($active, $id)
    {
        if ($active) {
            return $this->update(['active' => '0'], $id);
        }

        return $this->update(['active' => '1'], $id);

    }
}
