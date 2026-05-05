<?php

namespace App\Http\Controllers\ChangeRequests;

// use App\Factories\ChangeRequest\AttachmetsCRSFactory;
use App\Factories\ChangeRequest\ChangeRequestFactory;
// use App\Factories\ChangeRequest\ChangeRequestStatusFactory;
// use App\Factories\NewWorkFlow\NewWorkFlowFactory;
use App\Http\Controllers\Controller;

// use App\Http\Requests\Change_Request\Api\attachments_CRS_Request;
// use App\Http\Requests\Change_Request\Api\changeRequest_Requests;
// use App\Http\Resources\ChangeRequestListResource;
// use App\Http\Resources\ChangeRequestResource;
// use App\Http\Resources\MyAssignmentsCRSResource;
// use App\Http\Resources\MyCRSResource;

class ChangeRequestController extends Controller
{
    private $changerequest;
    // private $changerequeststatus;
    // private $workflow;

    public function __construct(ChangeRequestFactory $changerequest)
    {
        $this->changerequest = $changerequest::index();
        /*$this->changerequeststatus = $changerequeststatus::index();
        $this->changerworkflowequeststatus = $workflow::index();
        $this->attachments = $attachments::index();*/

        $title = 'Change Request List';
        $view = 'change_request';
        $route = 'change_request';
        $this->view = 'change_request';
        view()->share(compact('view', 'title', 'route'));
    }

    public function my_assignments()
    {

        $this->authorize('My Assignments'); // permission check

        $collection = $this->changerequest->my_assignments_crs();

        /* echo "<pre>";
        //print_r($collection[0]['RequestStatuses'][0]['status']['status_name']);
        print_r($collection['title']);
         echo "</pre>";
         dd('   oki');*/
        return view("$this->view.index", compact('collection'));
    }
}
