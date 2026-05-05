<?php

namespace App\Factories\Groups;

use App\Contracts\FactoryInterface;
use App\Http\Repository\Groups\GroupRepository;

class GroupFactory implements FactoryInterface
{
    public static function index()
    {
        return new GroupRepository();
    }
}
