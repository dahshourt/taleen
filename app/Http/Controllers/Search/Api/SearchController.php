<?php

namespace App\Http\Controllers\Search\Api;

use App\Factories\ChangeRequest\ChangeRequestFactory;
use App\Factories\ChangeRequest\ChangeRequestStatusFactory;
use App\Factories\NewWorkFlow\NewWorkFlowFactory;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdvancedSearchRequestResource;
use App\Http\Resources\SearchRequestResource;

// use App\Models\User;
// use App\Models\change_request;

// use App\Notifications\mail;
// use GuzzleHttp\Psr7\Request;
// use Notification;

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

        $cr = $this->changerequest->searhchangerequest($id);

        if ($cr) {
            $cr = new SearchRequestResource($cr);
        } else {
            $cr = null;
        }
        // dd($cr);

        return response()->json(['data' => $cr], 200);
    }

    public function AdvancedSearchResult()
    {

        $collection = $this->changerequest->AdvancedSearchResult();
        $collection = AdvancedSearchRequestResource::collection($collection);

        return response()->json(['data' => $collection], 200);
    }
}
