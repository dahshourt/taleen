<?php

namespace App\Factories\MailTemplates;

use App\Contracts\FactoryInterface;
use App\Http\Repository\MailTemplates\MailTemplatesRepository;

class MailTemplatesFactory implements FactoryInterface
{
    public static function index()
    {
        return new MailTemplatesRepository();
    }
}
