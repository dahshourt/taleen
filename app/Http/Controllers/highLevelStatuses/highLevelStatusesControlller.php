<?php

namespace App\Http\Controllers\highLevelStatuses;

use App\Factories\HighLevelStatuses\HighLevelStatusesFactory;
use App\Http\Controllers\Controller;
use App\Http\Repository\Statuses\StatusRepository;
use App\Http\Requests\HighLevelStatuses\highlevelrequest;
use App\Http\Resources\HighLevelStatusResource;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

class highLevelStatusesControlller extends Controller
{
    use ValidatesRequests;

    private $high_level_status;

    public function __construct(HighLevelStatusesFactory $high_level_status)
    {

        $this->high_level_status = $high_level_status::index();
        $this->view = 'high_level_status';
        $view = 'high_level_status';
        $route = 'high_level_status';
        $OtherRoute = 'high_level_status';

        $title = 'High Level Statuses';
        $form_title = 'High Level Status';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));

    }

    public function index()
    {

        $this->authorize('List HighLevelStatuses'); // permission check

        $collection = $this->high_level_status->paginateAll();

        return view("$this->view.index", compact('collection'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authorize('Create HighLevelStatus'); // permission check
        $statuses = StatusRepository::getAll();

        return view("$this->view.create", compact('statuses'));
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(highlevelrequest $request)
    {
        $this->authorize('Create HighLevelStatus'); // permission check
        $this->high_level_status->create($request->all());

        return redirect()->back()->with('status', 'Added Successfully');
    }

    public function edit($id)
    {
        $this->authorize('Edit HighLevelStatus'); // permission check
        $row = $this->high_level_status->find($id);
        $statuses = StatusRepository::getAll();

        return view("$this->view.edit", compact('row', 'statuses'));

    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(highlevelrequest $request, $id)
    {
        $this->authorize('Edit HighLevelStatus'); // permission check

        $this->high_level_status->update($request->except(['_token', '_method']), $id);

        return redirect()->back()->with('status', 'Updated Successfully');
    }

    public function show($id)
    {
        $this->authorize('Show HighLevelStatus'); // permission check
        $high_level_status = $this->high_level_status->find($id);
        if (! $high_level_status) {

            return redirect()->back()->with('failed', 'High_level_status Not Exists');
        }
        $high_level_status = new HighLevelStatusResource($high_level_status);
        $statuses = StatusRepository::getAll();
        $row = $high_level_status;

        return view("$this->view.edit", compact('row', 'statuses'));

    }

    public function destroy()
    {
        $this->authorize('Delete HighLevelStatus'); // permission check

    }

    public function updateactive(Request $request)
    {

        $this->authorize('Active HighLevelStatus'); // permission check

        $data = $this->high_level_status->find($request->id);

        $this->high_level_status->updateactive($data->active, $request->id);

        return response()->json([
            'message' => 'Updated Successfully',
            'status' => 'success',
        ]);

    }
}
