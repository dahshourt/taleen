<?php

namespace App\Http\Controllers\Workflow;

use App\Factories\Workflow\Workflow_type_factory;
use App\Http\Controllers\Controller;
// use App\Http\Requests\Workflow\DependWorkFlowRequest;
use Illuminate\Foundation\Validation\ValidatesRequests;

class Workflow_type extends Controller
{
    use ValidatesRequests;

    private $workflow_type;

    public function __construct(Workflow_type_factory $workflow_type_factory)
    {
        $this->workflow_type = $workflow_type_factory::index();
        $this->view = 'workflow_types';
        $view = 'workflow_types';
        $route = 'workflow_types';
        $OtherRoute = 'workflow_type';

        $title = 'workflow_types';
        $form_title = 'Workflow_type';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));
        // $this->workflow_type = $workflow_type_factory::index();

    }

    public function index()
    {
        $get_workflow_type = $this->workflow_type->get_workflow_type();
        $collection = $this->user->paginateAll();

        return view("$this->view.index", compact('get_workflow_type', 'collection'));
        // return response()->json(['data' => $get_workflow_type],200);
    }

    public function subtype($id)
    {

        $get_workflow_subtype = $this->workflow_type->get_workflow_subtype($id);

        return view("$this->view.index", compact('get_workflow_subtype'));
        // return response()->json(['data' => $get_workflow_subtype],200);
    }

    public function Allsubtype()
    {
        $get_workflow_subtype = $this->workflow_type->get_workflow_all_subtype();

        return view("$this->view.index", compact('get_workflow_subtype'));
        // return response()->json(['data' => $get_workflow_subtype],200);
    }
}
