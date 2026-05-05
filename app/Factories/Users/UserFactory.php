<?php

namespace App\Factories\Users;

use App\Contracts\FactoryInterface;
use App\Http\Repository\Users\UserRepository;

class UserFactory implements FactoryInterface
{
    public static function index()
    {
        return new UserRepository();
    }
}
