<?php

namespace App\Http\Controllers\Priorites\Api;

use App\Factories\Priorites\PriorityFactory;
use App\Http\Controllers\Controller;
// use App\Http\Requests\Priotitys\Api\PriotityRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;

class PriortyController extends Controller
{
    use ValidatesRequests;

    private $Priotity;

    public function __construct(PriorityFactory $Priotity)
    {

        $this->Priotity = $Priotity::index();

    }

    public function index()
    {
        $Priotites = $this->Priotity->getAll();

        return response()->json(['data' => $Priotites], 200);
    }
}
