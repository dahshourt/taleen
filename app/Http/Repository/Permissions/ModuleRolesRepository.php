<?php

namespace App\Http\Repository\Permissions;

use App\Contracts\Permissions\ModuleRoleRepositoryInterface;
use App\Models\Module_Rules;

class ModuleRolesRepository implements ModuleRoleRepositoryInterface
{
    public function getAll()
    {
        $m_roles = Module_Rules::where('active', '1')->get();

        return $m_roles;

    }
}
