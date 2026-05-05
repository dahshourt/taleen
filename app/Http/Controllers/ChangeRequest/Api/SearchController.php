<?php

namespace App\Http\Controllers\ChangeRequest\Api;

use App\Factories\ChangeRequest\ChangeRequestFactory;
use App\Factories\ChangeRequest\ChangeRequestStatusFactory;
use App\Factories\NewWorkFlow\NewWorkFlowFactory;
use App\Http\Controllers\Controller;

class SearchController extends Controller
{
    private $changerequest;

    private $changerequeststatus;

    private $workflow;

    public function __construct(ChangeRequestFactory $changerequest, ChangeRequestStatusFactory $changerequeststatus, NewWorkFlowFactory $workflow)
    {
        $this->changerequest = $changerequest::index();
        $this->changerequeststatus = $changerequeststatus::index();
        $this->changerworkflowequeststatus = $workflow::index();
    }

    public function search_result($id)
    {
        $cr = '39390';

        return response()->json(['data' => $cr], 200);
    }
}
