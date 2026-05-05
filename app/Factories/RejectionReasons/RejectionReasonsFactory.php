<?php

namespace App\Factories\RejectionReasons;

use App\Contracts\FactoryInterface;
use App\Http\Repository\RejectionReasons\RejectionReasonsRepository;

class RejectionReasonsFactory implements FactoryInterface
{
    public static function index()
    {
        return new RejectionReasonsRepository();
    }
}
