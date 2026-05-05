<?php

namespace App\Http\Controllers\SrCategories;

use App\Factories\SrCategories\SrCategoryFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\SrCategories\SrCategoryRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

class SrCategoryController extends Controller
{
    use ValidatesRequests;

    private $srCategory;
    private $view = 'sr_categories';

    public function __construct(SrCategoryFactory $srCategoryFactory)
    {
        $this->srCategory = $srCategoryFactory::index();

        $title = 'SR Categories';
        $view = 'sr_categories';
        $route = 'sr-categories';
        view()->share(compact('view', 'title', 'route'));
    }

    public function index()
    {
        $this->authorize('List SR Categories');
        $collection = $this->srCategory->paginateAll();
        return view("{$this->view}.index", compact('collection'));
    }

    public function create()
    {
        $this->authorize('Create SR Categories');
        $title = 'Create SR Category';
        return view("{$this->view}.create", compact('title'));
    }

    public function store(SrCategoryRequest $request)
    {
        $this->authorize('Create SR Categories');
        $this->srCategory->create($request->validated());
        return redirect()->route('sr-categories.index')->with('success', 'SR Category created successfully.');
    }

    public function edit(int $id)
    {
        $this->authorize('Edit SR Categories');
        $row = $this->srCategory->find($id);
        $title = 'Edit SR Category';
        return view("{$this->view}.edit", compact('row', 'title'));
    }

    public function update(SrCategoryRequest $request, int $id)
    {
        $this->authorize('Edit SR Categories');
        $this->srCategory->update($request->validated(), $id);
        return redirect()->route('sr-categories.index')->with('success', 'SR Category updated successfully.');
    }

    public function destroy(int $id)
    {
        $this->authorize('Delete SR Categories');
        $this->srCategory->delete($id);
        return redirect()->route('sr-categories.index')->with('success', 'SR Category deleted successfully.');
    }

    public function updateStatus(Request $request)
    {
        $this->authorize('Edit SR Categories');
        try {
            $this->srCategory->update(['active' => $request->status], $request->id);
            return response()->json([
                'success' => true,
                'message' => 'SR Category status updated successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating status.'
            ], 500);
        }
    }
}
