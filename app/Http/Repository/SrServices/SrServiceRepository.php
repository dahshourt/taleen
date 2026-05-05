<?php

namespace App\Http\Repository\SrServices;

use App\Contracts\SrServices\SrServiceRepositoryInterface;
use App\Models\SrService;

class SrServiceRepository implements SrServiceRepositoryInterface
{
    public function getAll()
    {
        return SrService::all();
    }

    public function create($request)
    {
        $service = SrService::create($request);
        if (isset($request['technical_teams'])) {
            $service->technicalTeams()->sync($request['technical_teams']);
        }
        return $service;
    }

    public function delete($id)
    {
        return SrService::destroy($id);
    }

    public function update($request, $id)
    {
        $service = SrService::find($id);
        if ($service) {
            // Unset technical_teams from request array if we are passing it to update
            // Not strictly necessary since it's not fillable, but good practice
            $techTeams = $request['technical_teams'] ?? [];
            unset($request['technical_teams']);

            $service->update($request);
            $service->technicalTeams()->sync($techTeams);
        }
        return $service;
    }

    public function paginateAll()
    {
        // Eager load category and hiddenFields to prevent N+1 queries in the loop
        return SrService::with(['category', 'hiddenFields'])->orderBy('id', 'desc')->paginate(50);
    }

    public function find($id)
    {
        return SrService::find($id);
    }
}
