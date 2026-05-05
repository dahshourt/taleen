<?php

namespace App\Contracts\ChangeRequest;

interface ChangeRequestRepositoryInterface
{
    public function create(array $request): array;

    public function ShowChangeRequestData($id, $group);

    public function getAll();

    public function find($id);

    // public function create($request);

    public function update($request, $id);

    public function delete($id);

    //  public function generate_end_date();
    public function my_crs();

    public function getRequesterSpecificCrs(int $userId): \Illuminate\Support\Collection;
}
