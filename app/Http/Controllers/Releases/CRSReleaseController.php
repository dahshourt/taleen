<?php

namespace App\Http\Controllers\Releases;

use App\Factories\Releases\ReleaseFactory;
use App\Http\Controllers\Controller;
use App\Http\Repository\ChangeRequest\ChangeRequestRepository;
use Exception;
use Illuminate\Http\Request;

class CRSReleaseController extends Controller
{
    private $release;

    public function __construct(ReleaseFactory $release)
    {

        $this->release = $release::index();
        $this->view = 'crs_related_to_releases';
        $view = 'crs_related_to_releases';
        $route = 'crs_related_to_releases';
        $OtherRoute = 'release';

        $title = 'CRs Related to Releases';
        $form_title = 'CRs Related to Releases';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function reorderhome()
    {
        $this->authorize('Release To CRs');

        return view("$this->view.shifiting");
    }
    // public function show_crs(Request $request)
    // {
    //     // Validate the incoming request
    //     $request->validate([
    //         'change_request_id' => 'required|exists:change_request,id',
    //     ]);

    //     // Extract the Change Request ID from the request
    //     $crId = $request->input('change_request_id');

    //     // Call the repository method to reorder times
    //     $r=new ChangeRequestRepository();
    //     $changeRequest = $r->findWithReleaseAndStatus($crId);

    //     if ($changeRequest && $changeRequest->release) {
    //         $release = $changeRequest->release;
    //         $releaseStatus = $release->releaseStatus;
    //     }

    //     return view("$this->view.shifiting", compact('changeRequest', 'release', 'releaseStatus','errorMessage'));

    // }

    public function show_crs(Request $request)
    {
        $this->authorize('CRs Related To Releases');

        $changeRequest = null;
        $release = null;
        $releaseStatus = null;
        $errorMessage = null;

        try {
            // Validate the incoming request
            $request->validate([
                'change_request_id' => 'required|exists:change_request,id',
            ]);

            // Extract the Change Request ID from the request
            $crId = $request->input('change_request_id');

            // Call the repository method to retrieve the Change Request
            $repository = new ChangeRequestRepository();
            $changeRequest = $repository->findWithReleaseAndStatus($crId); // $changeRequest->release_name
            $release = $this->release->find($changeRequest->release_name);

            if ($$release) {

                $releaseStatus = $release->releaseStatus;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors
            $errorMessage = 'Invalid Change Request ID. Please ensure it exists in the database.';
        } catch (Exception $e) {
            // Handle unexpected errors
            $errorMessage = 'An unexpected error occurred: ' . $e->getMessage();
        }

        // Return the view with data and error message if any
        return view("$this->view.result_release", compact('changeRequest', 'release', 'releaseStatus', 'errorMessage'));
    }
}
