<?php

namespace App\Http\Controllers\Categories\Api;

use App\Factories\Categories\CategoryFactory;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Validation\ValidatesRequests;

class CategoryController extends Controller
{
    use ValidatesRequests;

    private $Category;

    public function __construct(CategoryFactory $Category)
    {
        $this->Category = $Category::index();
    }

    public function index()
    {
        $Categorys = $this->Category->getAll();

        return response()->json(['data' => $Categorys], 200);
    }
}
