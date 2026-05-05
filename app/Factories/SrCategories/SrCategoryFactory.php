<?php

namespace App\Factories\SrCategories;

use App\Contracts\FactoryInterface;
use App\Http\Repository\SrCategories\SrCategoryRepository;

class SrCategoryFactory implements FactoryInterface
{
    public static function index()
    {
        return new SrCategoryRepository();
    }
}
