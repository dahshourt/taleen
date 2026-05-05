<?php

namespace App\Http\Controllers\Director;

use App\Http\Controllers\Controller;
use App\Http\Requests\Director\DirectorRequest;
use App\Services\DirectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DirectorController extends Controller
{
    protected $directorService;

    public function __construct(DirectorService $directorService)
    {
        $this->directorService = $directorService;
        $this->view = 'director';
        $view = 'director';
        $route = 'directors';
        $title = 'Directors';
        $form_title = 'Director';
        $OtherRoute = 'directors';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));
    }

    public function index(): View
    {
        $this->authorize('List Director');

        $collection = $this->directorService->getAllDirectors(true);

        return view("$this->view.index", compact('collection'));
    }

    public function create(): View
    {
        $this->authorize('Create Director');

        return view("$this->view.create");
    }

    public function store(DirectorRequest $request): RedirectResponse
    {
        $this->authorize('Create Director');

        $this->directorService->createDirector($request->validated());

        return redirect()->back()->with('status', 'Created Successfully');
    }

    public function edit($id): View
    {
        $this->authorize('Edit Director');

        $row = $this->directorService->findDirector($id);

        return view("$this->view.edit", compact('row'));
    }

    public function update(DirectorRequest $request, $id): RedirectResponse
    {
        $this->authorize('Edit Director');

        $this->directorService->updateDirector($request->validated(), $id);

        return redirect()->back()->with('status', 'Updated Successfully');
    }

    public function updateStatus(Request $request): JsonResponse
    {
        $this->authorize('Edit Director');

        $this->directorService->updateDirectorStatus($request->post('id'));

        return response()->json([
            'message' => 'Updated Successfully',
            'status' => 'success',
        ]);
    }
}
