<?php

namespace App\Http\Controllers\Sla;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\GroupStatuses;
use App\Models\SlaCalculation;
use App\Models\Status;
use Illuminate\Http\Request;

class SlaCalculationController extends Controller
{
    public function __construct()
    {
        view()->share([
            'view' => 'sla.calculations',
            'route' => 'sla-calculations',
            'form_title' => 'SLA Calculation',
        ]);
    }

    public function index()
    {
        $this->authorize('SLA Calculations');
        view()->share('title', 'SLA Calculations');
        $calculations = SlaCalculation::with('status')->get();

        return view('sla.calculations.index', compact('calculations'));
    }

    public function create()
    {
        $this->authorize('Create SLA');
        view()->share('title', 'Create SLA Calculation');
        $statuses = Status::all();
        $units = Unit::all();
        return view('sla.calculations.create', compact('statuses', 'units'));
    }

    public function getGroups($status_id)
    {
        $groups = GroupStatuses::with('group')
            ->where('status_id', $status_id)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->group_id,
                    'name' => $item->group ? $item->group->title : null,
                ];
            });

        return response()->json($groups);
    }

    /*public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_sla_time'     => 'required|integer|min:1',
            'division_sla_time' => 'required|integer|min:1',
            'director_sla_time' => 'required|integer|min:1',
            'sla_type_unit'          => 'required|in:day,hour',
            'sla_type_division'          => 'required|in:day,hour',
            'sla_type_director'          => 'required|in:day,hour',
            'status_id'     => 'required|exists:statuses,id',
            'group_id'      => 'required|exists:groups,id',
        ]);

        SlaCalculation::create($validated);

        return redirect()->route('sla-calculations.index')
                         ->with('success', 'SLA Calculation created successfully.');
    }*/

    public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_sla_time' => 'nullable|integer|min:1',
            'division_sla_time' => 'nullable|integer|min:1',
            'director_sla_time' => 'nullable|integer|min:1',
            'sla_type_unit' => 'nullable|in:day,hour',
            'sla_type_division' => 'nullable|in:day,hour',
            'sla_type_director' => 'nullable|in:day,hour',
            'status_id' => 'required|exists:statuses,id',
           // 'unit_id' => 'required|exists:units,id', 
            'unit_notification' => 'nullable|boolean',
            'division_notification' => 'nullable|boolean',
            'director_notification' => 'nullable|boolean',
        ]);
       // dd($request);
        // ✅ Ensure at least one SLA level is provided
        if (
            empty($validated['unit_sla_time']) &&
            empty($validated['division_sla_time']) &&
            empty($validated['director_sla_time'])
        ) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['at_least_one' => 'You must provide at least one SLA level (unit, division, or director).']);
        }

        // ✅ Hierarchy Validation: must follow Unit → Division → Director
        if (! empty($validated['division_sla_time']) && empty($validated['unit_sla_time'])) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['hierarchy' => 'Division SLA cannot be set without Unit SLA.']);
        }

        if (! empty($validated['director_sla_time']) &&
            (empty($validated['unit_sla_time']) || empty($validated['division_sla_time']))) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['hierarchy' => 'Director SLA cannot be set without Unit and Division SLAs.']);
        }

        // ✅ Check for duplicate (same group_id + status_id)
        
        // $exists = SlaCalculation::where('unit_id', $validated['unit_id'])
        //     ->where('status_id', $validated['status_id'])
        //     ->exists();

        // if ($exists) {
        //     return redirect()->back()
        //         ->withInput()
        //         ->withErrors(['duplicate' => 'This SLA Calculation already exists for the selected Unit and status.']);
        // }
      //  dd($validated);
        // ✅ Create new record
        SlaCalculation::create($validated);

        return redirect()->route('sla-calculations.index')
            ->with('success', 'SLA Calculation created successfully.');
    }

    public function show(SlaCalculation $slaCalculation)
    {
        return view('sla.calculations.show', compact('slaCalculation'));
    }

    public function edit(SlaCalculation $slaCalculation)
    {
        $this->authorize('Edit SLA');
        view()->share('title', 'Edit SLA Calculation');
        $statuses = Status::all();
        $units = Unit::all();
        $row = SlaCalculation::with('status')->find($slaCalculation->id);

        return view('sla.calculations.edit', compact('slaCalculation', 'row', 'statuses', 'units'));
    }

    public function update(Request $request, SlaCalculation $slaCalculation)
    {
        $validated = $request->validate([
            'unit_sla_time' => 'required|integer|min:1',
            'division_sla_time' => 'required|integer|min:1',
            'director_sla_time' => 'required|integer|min:1',
            'sla_type_unit' => 'required|in:day,hour',
            'sla_type_division' => 'required|in:day,hour',
            'sla_type_director' => 'required|in:day,hour',
            'status_id' => 'required|exists:statuses,id',
            //'unit_id' => 'required|exists:units,id',
        ]);

        $slaCalculation->update($validated);

        return redirect()->route('sla-calculations.index')
            ->with('success', 'SLA Calculation updated successfully.');
    }

    public function destroy(SlaCalculation $slaCalculation)
    {

        $slaCalculation->delete();

        return redirect()->route('sla-calculations.index')
            ->with('success', 'SLA Calculation deleted successfully.');
    }
}
