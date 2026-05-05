<?php

namespace App\Http\Controllers\SystemTeValidator;

use App\Factories\SystemTeValidator\SystemTeValidatorFactory;
use App\Http\Controllers\Controller;
use App\Http\Repository\Applications\ApplicationRepository;
use App\Http\Repository\Users\UserRepository;
use App\Http\Requests\SystemTeValidator\SystemTeValidatorRequest;
use Illuminate\Http\Request;

class SystemTeValidatorController extends Controller
{
    private $system_te_validator;

    public function __construct(SystemTeValidatorFactory $system_te_validator)
    {
        $this->system_te_validator = $system_te_validator::index();
        $this->view = 'system_te_validator';
        $view = 'system_te_validator';
        $route = 'system_te_validators';
        $OtherRoute = 'system_te_validator';
        $title = 'System TE Validators';
        $form_title = 'System TE Validator';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));
    }

    public function index()
    {
        // $this->authorize('List System TE Validators'); // Optional permission check depending on user implementation
        $collection = $this->system_te_validator->getAll();
        return view("$this->view.index", compact('collection'));
    }

    public function create()
    {
        // $this->authorize('Create System TE Validator'); 
        $users = (new UserRepository)->getAllWithActive();
        $applications = (new ApplicationRepository)->getAll();
        return view("$this->view.create", compact('users', 'applications'));
    }

    public function store(SystemTeValidatorRequest $request)
    {
        $this->system_te_validator->create($request->all());
        return redirect()->route($this->route ?? 'system_te_validators.index')->with('status', 'Added Successfully');
    }

    public function edit($id)
    {
        // $this->authorize('Edit System TE Validator'); 
        $row = $this->system_te_validator->find($id);
        $users = (new UserRepository)->getAllWithActive();
        $applications = (new ApplicationRepository)->getAll();
        return view("$this->view.edit", compact('row', 'users', 'applications'));
    }

    public function update(SystemTeValidatorRequest $request, $id)
    {
        $this->system_te_validator->update($request->except(['_token', '_method']), $id);
        return redirect()->route($this->route ?? 'system_te_validators.index')->with('status', 'Updated Successfully');
    }

    public function destroy($id)
    {
        // $this->authorize('Delete System TE Validator'); 
        $this->system_te_validator->delete($id);
        return redirect()->route($this->route ?? 'system_te_validators.index')->with('status', 'Deleted Successfully');
    }

    public function updateactive(Request $request)
    {
        // $this->authorize('Active System TE Validator'); 
        $data = $this->system_te_validator->find($request->id);
        $this->system_te_validator->updateactive($data->active, $request->id);

        return response()->json([
            'message' => 'Updated Successfully',
            'status' => 'success',
        ]);
    }
}
