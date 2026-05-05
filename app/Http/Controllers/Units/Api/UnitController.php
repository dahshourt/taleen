<?php

namespace App\Http\Controllers\Units\Api;

use App\Factories\Units\UnitFactory;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Validation\ValidatesRequests;

class UnitController extends Controller
{
    use ValidatesRequests;

    private $Unit;

    public function __construct(UnitFactory $Unit)
    {

        $this->Unit = $Unit::index();

    }

    public function index()
    {
        $Units = $this->Unit->getAll();

        return response()->json(['data' => $Units], 200);
    }
}
