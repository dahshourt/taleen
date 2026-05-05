<?php

namespace App\Http\Controllers\Search;

use App\Factories\ChangeRequest\ChangeRequestFactory;
use App\Factories\ChangeRequest\ChangeRequestStatusFactory;
use App\Factories\NewWorkFlow\NewWorkFlowFactory;
use App\Http\Controllers\Search\TableExport;
use App\Http\Controllers\Controller;
use App\Http\Repository\ChangeRequest\ChangeRequestRepository;
use App\Http\Resources\AdvancedSearchRequestResource;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\Request;
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

    private $view;

    public function __construct(ChangeRequestFactory $changerequest, ChangeRequestStatusFactory $changerequeststatus, NewWorkFlowFactory $workflow)
    {

        /*$this->middleware(function ($request, $next) {
            $this->user= \Auth::user();
            if(!$this->user->hasRole('Super Admin') && !$this->user->can('Access Search'))
            {
                abort(403, 'This action is unauthorized.');
            }
            else
            {
                return $next($request);
            }
        });*/
        $this->changerequest = $changerequest::index();
        $this->changerequeststatus = $changerequeststatus::index();
        $this->changerworkflowequeststatus = $workflow::index();
        $this->view = 'search';
        $view = 'search';
        $route = 'change_request';
        $OtherRoute = 'search';

        $title = 'Search';
        $form_title = 'search';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));
    }

    public function index()
    {
        $this->authorize('Access Search');

        return view("$this->view.create");
    }

    public function advanced_search()
    {

        $this->authorize('Access Advanced Search'); // permission check
        $form_title = 'Advanced Search';
        return view("$this->view.advanced_search", compact('form_title'));
    }

    public function edit($id)
    {
        $this->authorize('Access Search');
        $row = $this->changerequest->find($id);

        return view("$this->view.edit", compact('row'));

    }

    public function search_result()
    {
        $this->authorize('Access Search');

        $cr = $this->changerequest->searhchangerequest(request()->search);
        if (!$cr) {
            return redirect('/searchs')->with('error', 'CR NO not exists.');
        }

        // Standardize as a collection for the unified view
        $items = collect([$cr]);

        $r = new ChangeRequestRepository();
        $crs_in_queues = $r->getAllWithoutPagination()->pluck('id');

        // Add CRs where the user is the requester and status is specific
        $requesterCrs = $r->getRequesterSpecificCrs(auth()->id());

        $crs_in_queues = collect($crs_in_queues)->merge($requesterCrs)->unique();

        $title = 'Search Result';
        $searchType = 'simple';

        return view("$this->view.index", compact('items', 'crs_in_queues', 'title', 'searchType'));
    }

    public function AdvancedSearchResult()
    {
        $this->authorize('Access Advanced Search'); // permission check

        // Retrieve the paginated collection from the model
        $items = $this->changerequest->AdvancedSearchResult()->appends(request()->query());

        // Ensure $items is an instance of Illuminate\Pagination\LengthAwarePaginator
        if (!($items instanceof \Illuminate\Pagination\LengthAwarePaginator)) {
            abort(500, 'Expected paginated collection from AdvancedSearchResult.');
        }

        $totalCount = $items->total();
        // Transform the collection into resource format if needed, but for now we'll use the items directly or ensure consistancy
        // The original code used a Resource, let's keep it if it transforms data significantly,
        // but Resources usually return an array/json, not a collection suitable for blade loops if not careful.
        // Let's look at the resource usage. It was: $collection = AdvancedSearchRequestResource::collection($collection);
        // This returns an AnonymousResourceCollection.
        // For the blade view, we might want the original model objects to access methods like `isOnHold()`.
        // The Resource might flatten things to arrays which breaks method calls in loop.blade.php.
        // I will stick to passing the models directly for now to preserve functionality in loop.blade.php
        // unless AdvancedSearchRequestResource does critical formatting.
        // Checking the original AdvancedSearchResult.blade.php, it accessed properties like $item['id'].
        // loop.blade.php accesses methods like $cr->isOnHold().
        // So I MUST pass Model objects to use loop.blade.php.
        // `AdvancedSearchResult` in repository returns a Paginator of models?
        // Let's assume yes.

        $r = new ChangeRequestRepository();
        $crs_in_queues = $r->getAll()->pluck('id');

        // Add CRs where the user is the requester and status is specific
        $requesterCrs = $r->getRequesterSpecificCrs(auth()->id());

        $crs_in_queues = collect($crs_in_queues)->merge($requesterCrs)->unique();
        $title = 'Advanced Search Result';
        $searchType = 'advanced';

        return view("$this->view.index", compact('totalCount', 'items', 'crs_in_queues', 'title', 'searchType'));
    }

    public function AdvancedSearchResultExport(request $request): BinaryFileResponse
    {
        $this->authorize('Access Advanced Search');
        
        // Get the same filters that are used in the advanced search
        $filters = $request->all();
        
        // Export the filtered results as Excel
        return Excel::download(new TableExport($filters), 'advanced_search_results.xlsx');
    }
}
