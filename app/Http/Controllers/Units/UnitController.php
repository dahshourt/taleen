<?php

namespace App\Http\Controllers\Units;

use App\Http\Controllers\Controller;
use App\Http\Requests\Units\UnitsRequest;
use App\Services\UnitsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitController extends Controller
{
    protected $unitsService;

    public function __construct(UnitsService $unitsService)
    {
        $this->unitsService = $unitsService;
        $this->view = 'units';
        $view = 'units';
        $route = 'units';
        $title = 'Units';
        $form_title = 'Unit';
        $OtherRoute = 'units';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));
    }

    public function index(): View
    {
        $this->authorize('List Units');

        $collection = $this->unitsService->getAllUnits(true);

        return view("$this->view.index", compact('collection'));
    }

    public function create(): View
    {
        $this->authorize('Create Units');

        return view("$this->view.create");
    }

    public function store(UnitsRequest $request): RedirectResponse
    {
        $this->authorize('Create Units');

        $this->unitsService->createUnit($request->validated());

        return redirect()->back()->with('status', 'Created Successfully');
    }

    public function edit($id): View
    {
        $this->authorize('Edit Units');

        $row = $this->unitsService->findUnit($id);

        return view("$this->view.edit", compact('row'));
    }

    public function update(UnitsRequest $request, $id): RedirectResponse
    {
        $this->authorize('Edit Units');

        $this->unitsService->updateUnit($request->validated(), $id);

        return redirect()->back()->with('status', 'Updated Successfully');
    }

    public function updateStatus(Request $request): JsonResponse
    {
        $this->authorize('Edit Units');

        $this->unitsService->updateUnitStatus($request->post('id'));

        return response()->json([
            'message' => 'Updated Successfully',
            'status' => 'success',
        ]);
    }
}
