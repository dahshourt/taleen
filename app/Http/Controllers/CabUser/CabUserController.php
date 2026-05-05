<?php

namespace App\Http\Controllers\CabUser;

use App\Factories\CabUser\CabUserFactory;
use App\Http\Controllers\Controller;
use App\Http\Repository\Applications\ApplicationRepository;
use App\Http\Repository\Users\UserRepository;
use App\Http\Requests\CabUser\CabUserRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

class CabUserController extends Controller
{
    // use ValidatesRequests;
    private $cab_user;

    public function __construct(CabUserFactory $cab_user)
    {
        $this->cab_user = $cab_user::index();
        $this->view = 'cab_user';
        $view = 'cab_user';
        $route = 'cab_users';
        $OtherRoute = 'cab_user';
        $title = 'Cab Users';
        $form_title = 'Cab User';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));
    }

    public function index()
    {
        $this->authorize('List Cab Users'); // permission check
        $collection = $this->cab_user->getAll();

        return view("$this->view.index", compact('collection'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authorize('Create Cab User'); // permission check
        $users = (new UserRepository)->getAllWithActive();
        $applications = (new ApplicationRepository)->getAll();

        return view("$this->view.create", compact('users', 'applications'));
    }

    /**
     * Send or resend the verification code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(CabUserRequest $request)
    {
        $this->cab_user->create($request->all());

        return redirect()->back()->with('status', 'Added Successfully');
    }

    public function edit($id)
    {
        $this->authorize('Edit Cab User'); // permission check
        $row = $this->division_manager->find($id);

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
    public function update(CabUserRequest $request, $id)
    {
        $this->cab_user->update($request->except(['_token', '_method']), $id);

        return redirect()->back()->with('status', 'Updated Successfully');
    }

    public function destroy()
    {
        $this->authorize('Delete Cab User'); // permission check

    }

    public function updateactive(Request $request)
    {
        $this->authorize('Active Cab User'); // permission check

        $data = $this->cab_user->find($request->id);
        $this->cab_user->updateactive($data->active, $request->id);

        return response()->json([
            'message' => 'Updated Successfully',
            'status' => 'success',
        ]);
    } // end method

}
