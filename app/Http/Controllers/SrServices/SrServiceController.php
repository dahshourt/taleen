<?php

namespace App\Http\Controllers\SrServices;

use App\Factories\SrServices\SrServiceFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\SrServices\SrServiceRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use App\Models\SrCategory;
use App\Models\Group;
use App\Models\CustomField;

class SrServiceController extends Controller
{
    use ValidatesRequests;

    private $srService;
    private $view = 'sr_services';

    public function __construct(SrServiceFactory $srServiceFactory)
    {
        $this->srService = $srServiceFactory::index();

        $title = 'SR Services';
        $view = 'sr_services';
        $route = 'sr-services';
        view()->share(compact('view', 'title', 'route'));
    }

    public function index()
    {
        $this->authorize('List SR Services');
        $collection = $this->srService->paginateAll();
        return view("{$this->view}.index", compact('collection'));
    }

    public function create()
    {
        $this->authorize('Create SR Services');
        $title = 'Create SR Service';
        $categories = SrCategory::where('active', 1)->get();
        $technical_teams = Group::where('sr_technical_team', 1)->get();
        return view("{$this->view}.create", compact('title', 'categories', 'technical_teams'));
    }

    public function store(SrServiceRequest $request)
    {
        $this->authorize('Create SR Services');
        $this->srService->create($request->validated());
        return redirect()->route('sr-services.index')->with('success', 'SR Service created successfully.');
    }

    public function edit(int $id)
    {
        $this->authorize('Edit SR Services');
        $row = $this->srService->find($id);
        $title = 'Edit SR Service';
        $categories = SrCategory::where('active', 1)->get();
        $technical_teams = Group::where('sr_technical_team', 1)->get();
        return view("{$this->view}.edit", compact('row', 'title', 'categories', 'technical_teams'));
    }

    public function update(SrServiceRequest $request, int $id)
    {
        $this->authorize('Edit SR Services');
        $this->srService->update($request->validated(), $id);
        return redirect()->route('sr-services.index')->with('success', 'SR Service updated successfully.');
    }

    public function destroy(int $id)
    {
        $this->authorize('Delete SR Services');
        $this->srService->delete($id);
        return redirect()->route('sr-services.index')->with('success', 'SR Service deleted successfully.');
    }

    public function updateStatus(Request $request)
    {
        $this->authorize('Edit SR Services');
        try {
            $this->srService->update(['active' => $request->status], $request->id);
            return response()->json([
                'success' => true,
                'message' => 'SR Service status updated successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating status.'
            ], 500);
        }
    }

    public function getByCategory(Request $request)
    {
        $categoryId = $request->input('category_id');
        if (empty($categoryId)) {
            return response()->json(['success' => false, 'message' => 'Category ID is required.']);
        }

        $services = \App\Models\SrService::where('sr_category_id', $categoryId)
            ->where('active', 1)
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'services' => $services
        ]);
    }

    public function manageHiddenFields(int $id)
    {
        $this->authorize('Edit SR Services');
        $service = \App\Models\SrService::with(['hiddenFields', 'category'])->findOrFail($id);
        $title = 'Manage Hidden Fields: ' . $service->name;
        $custom_fields = CustomField::get();
        return view("{$this->view}.manage_hidden_fields", compact('service', 'title', 'custom_fields'));
    }

    public function updateHiddenFields(Request $request, int $id)
    {
        $this->authorize('Edit SR Services');
        $service = \App\Models\SrService::findOrFail($id);
        $service->hiddenFields()->sync($request->input('hidden_fields', []));
        return redirect()->route('sr-services.index')->with('success', 'Hidden fields updated successfully.');
    }

    public function getHiddenFields(Request $request)
    {
        $serviceId = $request->input('service_id');
        if (empty($serviceId)) {
            return response()->json(['success' => false, 'message' => 'Service ID is required.']);
        }

        $service = \App\Models\SrService::with(['hiddenFields', 'technicalTeams'])->find($serviceId);
        
        if (!$service) {
            return response()->json(['success' => false, 'message' => 'Service not found.']);
        }

        $hiddenFieldNames = $service->hiddenFields->pluck('name');
        $technicalTeams = $service->technicalTeams->map(function($team) {
            return [
                'id' => $team->id,
                'title' => $team->title
            ];
        });

        return response()->json([
            'success' => true,
            'hidden_fields' => $hiddenFieldNames,
            'technical_teams' => $technicalTeams
        ]);
    }
}
