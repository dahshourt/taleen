<?php

namespace App\Http\Controllers\Departments\Api;

use App\Factories\Departments\DepartmentFactory;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Validation\ValidatesRequests;

class DepartmentController extends Controller
{
    use ValidatesRequests;

    private $Department;

    public function __construct(DepartmentFactory $Department)
    {

        $this->Department = $Department::index();

    }

    public function index()
    {
        $Departments = $this->Department->getAll();

        return response()->json(['data' => $Departments], 200);
    }
}
