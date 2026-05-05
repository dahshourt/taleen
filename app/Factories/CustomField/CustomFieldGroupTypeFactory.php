<?php

namespace App\Factories\CustomField;

use App\Contracts\FactoryInterface;
use App\Http\Repository\CustomField\CustomFieldGroupTypeRepository;

class CustomFieldGroupTypeFactory implements FactoryInterface
{
    public static function index()
    {
        return new CustomFieldGroupTypeRepository();
    }
}
