<?php

namespace App\Http\Repository\Systems;

use App\Contracts\Systems\SystemRepositoryInterface;
// declare Entities
use App\Models\Application;

class SystemRepository implements SystemRepositoryInterface
{
    public function getAll()
    {
        return Application::with('workflow_type')->get();
    }

    public function create($request)
    {
        return Application::create($request);
    }

    public function delete($id)
    {
        return Application::destroy($id);
    }

    public function update($request, $id)
    {
        return Application::where('id', $id)->update($request);
    }

    public function find($id)
    {
        return Application::find($id);
    }

    public function updateactive($active, $id)
    {
        if ($active) {
            return $this->update(['active' => '0'], $id);
        }

        return $this->update(['active' => '1'], $id);

    }
}
