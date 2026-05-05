<?php

namespace App\Http\Controllers\RejectionReasons;

use App\Factories\RejectionReasons\RejectionReasonsFactory;
use App\Http\Controllers\Controller;
use App\Http\Repository\Workflow\Workflow_type_repository;
use App\Http\Requests\RejectionReasons\RejectionReasonsRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

class RejectionReasonsController extends Controller
{
    use ValidatesRequests;

    private $RejectionReasons;

    public function __construct(RejectionReasonsFactory $RejectionReasons)
    {

        $this->rejectionReason = $RejectionReasons::index();
        $this->view = 'rejection_reasons';
        $view = 'rejection_reasons';
        $route = 'rejection_reasons';
        $OtherRoute = 'rejection_reasons'; // rejection_reasons

        $title = 'Rejection Reasons';
        $form_title = 'Rejection Reason';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));

    }

    public function index()
    {
        $this->authorize('List RejectionReasons'); // permission check
        $collection = $this->rejectionReason->paginateAll();

        return view("$this->view.index", compact('collection'));
    }

    public function create()
    {
        //
        $this->authorize('Create RejectionReason'); // permission check

        $types = (new Workflow_type_repository)->get_workflow_all_subtype();

        return view("$this->view.create", compact('types'));
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(RejectionReasonsRequest $request)
    {
        $this->authorize('Create RejectionReason'); // permission check

        $this->rejectionReason->create($request->all());

        return redirect()->route("$this->view.index")->with('message', 'Created Successfully');

    }

    public function edit($id)
    {

        $this->authorize('Edit RejectionReason'); // permission check
        $row = $this->rejectionReason->find($id);
        $types = (new Workflow_type_repository)->get_workflow_all_subtype();

        return view("$this->view.edit", compact('row', 'types'));

    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(RejectionReasonsRequest $request, $id)
    {
        $this->authorize('Edit RejectionReason'); // permission check
        $this->rejectionReason->update($request->except(['_token', '_method']), $id);

        return redirect()->back()->with('status', 'Updated Successfully');
        // return redirect()->route('rejection_reason.index')->with('status' , 'Updated Successfully' );

    }

    public function show($id)
    {

        $this->authorize('Show RejectionReason'); // permission check
        $RejectionReasons = $this->rejectionReason->find($id);
        if (! $RejectionReasons) {
            return response()->json([
                'message' => 'system Not Exists',
            ], 422);
        }

        return response()->json(['data' => $RejectionReasons], 200);
    }

    public function StageStatuses($id)
    {
        $RejectionReasons = $this->rejectionReason->find($id);
        if (! $system) {
            return response()->json([
                'message' => 'system Not Exists',
            ], 422);
        }

        return response()->json(['data' => $RejectionReasons->statuses], 200);
    }

    public function destroy()
    {
        $this->authorize('Delete RejectionReason'); // permission check

    }

    public function updateactive(Request $request)
    {

        $this->authorize('Active RejectionReason'); // permission check
        $id = $request->id;

        $RejectionReasons = $this->rejectionReason->find($id);

        $this->rejectionReason->updateactive($RejectionReasons['active'], $id);

        return response()->json([
            'message' => 'Updated Successfully',
            'status' => 'success',
        ]);

    }
}
