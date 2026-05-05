<?php

namespace App\Factories\CustomField;

use App\Contracts\FactoryInterface;
use App\Http\Repository\CustomField\CustomFieldRepository;

class CustomFieldFactory implements FactoryInterface
{
    public static function index()
    {
        return new CustomFieldRepository();
    }
}
