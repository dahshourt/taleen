<?php

namespace App\Http\Repository\SystemTeValidator;

use App\Contracts\SystemTeValidator\SystemTeValidatorRepositoryInterface;
use App\Models\SystemTeValidator;

class SystemTeValidatorRepository implements SystemTeValidatorRepositoryInterface
{
    public function getAll()
    {
        return SystemTeValidator::latest()->paginate(10);
    }

    public function create($request)
    {
        $item = SystemTeValidator::create($request);
        return $item;
    }

    public function delete($id)
    {
        return SystemTeValidator::destroy($id);
    }

    public function update($request, $id)
    {
        $item = SystemTeValidator::where('id', $id)->update($request);
        return $item;
    }

    public function find($id)
    {
        return SystemTeValidator::find($id);
    }

    public function updateactive($active, $id)
    {
        if ($active) {
            SystemTeValidator::where('id', $id)->update(['active' => '0']);
        } else {
            SystemTeValidator::where('id', $id)->update(['active' => '1']);
        }

        return true;
    }

    public function getUsersBySystem($system_id)
    {
        return SystemTeValidator::where('system_id', $system_id)->where('active', '1')->get();
    }
}
