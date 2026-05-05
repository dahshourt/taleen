<?php

namespace App\Http\Controllers\Stages;

use App\Factories\Stages\StageFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stages\StageRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

class StageController extends Controller
{
    use ValidatesRequests;

    private $stage;

    public function __construct(StageFactory $stage)
    {

        $this->stage = $stage::index();
        $this->view = 'stages';
        $view = 'stages';
        $route = 'stages';
        $OtherRoute = 'stage';

        $title = 'Stages';
        $form_title = 'Stage';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));

    }

    public function index()
    {
        $this->authorize('List Stages'); // permission check

        $collection = $this->stage->paginateAll();

        return view("$this->view.index", compact('collection'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authorize('Create Stage'); // permission check
        //

        return view("$this->view.create");
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(StageRequest $request)
    {
        $this->authorize('Create Stage'); // permission check
        $this->stage->create($request->all());

        return redirect()->route('stages.index')->with('status', 'Added Successfully');
    }

    public function edit($id)
    {
        $this->authorize('Edit Stage'); // permission check
        $row = $this->stage->find($id);

        return view("$this->view.edit", compact('row'));

    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(StageRequest $request, $id)
    {
        $this->authorize('Edit Stage'); // permission check

        $this->stage->update($request->except(['_token', '_method']), $id);

        return redirect()->route('stages.index')->with('status', 'Updated Successfully');
    }

    public function show($id)
    {
        $this->authorize('Show Stage'); // permission check
        $stage = $this->stage->find($id);
        if (! $stage) {
            return response()->json([
                'message' => 'Stage Not Exists',
            ], 422);
        }
        $stage = new StagesResource($stage);

        return response()->json(['data' => $stage], 200);
    }

    public function destroy()
    {
        $this->authorize('Delete Stage'); // permission check

    }

    public function updateactive(Request $request)
    {

        $this->authorize('Active Stage'); // permission check

        $data = $this->stage->find($request->id);

        $this->stage->updateactive($data->active, $request->id);

        return response()->json([
            'message' => 'Updated Successfully',
            'status' => 'success',
        ]);

    }

    public function get_stages_with_group_and_role($role_id, $default_group)
    {

        $stage = $this->stage->get_stages_with_group_and_role($role_id, $default_group);

        return response()->json([
            'data' => $stage,
        ], 200);
    }
}
