<?php

namespace App\Http\Repository\CabUser;

use App\Contracts\CabUser\CabUserRepositoryInterface;
use App\Models\SystemUserCab;

class CabUserRepository implements CabUserRepositoryInterface
{
    public function getAll()
    {
        return SystemUserCab::latest()->paginate(10);
    }

    public function create($request)
    {

        $item = SystemUserCab::create($request);

        return $item;
    }

    public function delete($id)
    {
        return SystemUserCab::destroy($id);
    }

    public function update($request, $id)
    {

        $item = SystemUserCab::where('id', $id)->update($request);

        return $item;
    }

    public function find($id)
    {
        return SystemUserCab::find($id);
    }

    public function updateactive($active, $id)
    {
        if ($active) {
            SystemUserCab::where('id', $id)->update(['active' => '0']);
        } else {
            SystemUserCab::where('id', $id)->update(['active' => '1']);
        }

        return true;
    }

    public function getUsersBySystem($system_id)
    {
        return SystemUserCab::where('system_id', $system_id)->where('active', '1')->get();
    }
}
