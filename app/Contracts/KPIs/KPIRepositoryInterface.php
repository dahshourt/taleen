<?php

namespace App\Contracts\KPIs;

interface KPIRepositoryInterface
{
    public function getAll();

    public function find($id);

    public function create($request);

    public function update($request, $id);

    public function delete($id);

    public function attachChangeRequestByNumber($kpiId, $crNo);

    public function detachChangeRequest($kpiId, $crId);
}
