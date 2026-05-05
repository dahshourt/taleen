<?php

namespace App\Http\Controllers\Applications\Api;

use App\Factories\Applications\ApplicationFactory;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Validation\ValidatesRequests;

class ApplicationController extends Controller
{
    use ValidatesRequests;

    private $Application;

    public function __construct(ApplicationFactory $Application)
    {

        $this->Application = $Application::index();

    }

    public function index()
    {
        $Applications = $this->Application->getAll();

        return response()->json(['data' => $Applications], 200);
    }
}
