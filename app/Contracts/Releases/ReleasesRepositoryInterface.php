<?php

namespace App\Contracts\Releases;

interface ReleasesRepositoryInterface
{
    public function getAll();

    public function create($request);
}
