<?php

namespace App\Http\Controllers\Releases;

use App\Factories\Releases\ReleaseFactory;
use App\Http\Controllers\Controller;
use App\Http\Repository\ChangeRequest\ChangeRequestRepository;
use App\Http\Requests\Releases\ReleaseRequest;
use App\Http\Resources\releaseResource;
use App\Models\NewWorkFlow;
use App\Models\ReleaseCrAttachment;
use App\Models\ReleaseLogs;
use App\Models\ReleaseTeamMember;
use App\Models\ReleaseTeamRole;
use App\Models\Status;
use App\Models\WorkFlowType;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ReleaseController extends Controller
{
    private $release;

    public function __construct(ReleaseFactory $release)
    {

        $this->release = $release::index();
        $this->view = 'releases';
        $view = 'releases';
        $route = 'releases';
        $OtherRoute = 'release';

        $title = 'Releases';
        $form_title = 'Release';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->authorize('List Release');

        $collection = $this->release->paginateAll();

        return view("$this->view.index", compact('collection'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authorize('Create Release');

        $vendors = \App\Models\Vendor::where('active', '1')->get();
        $priorities = \App\Models\Priority::all();
        $applications = \App\Models\Application::where('active', '1')->get();
        $rtmUsers = \App\Models\User::all(); // Filter by role if needed
        
        return view("$this->view.create", compact('vendors', 'priorities', 'applications', 'rtmUsers'));
    }

    public function reorderhome()
    {
        $this->authorize('Release To CRs');

        return view("$this->view.shifiting");
    }

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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(ReleaseRequest $request)
    {
        $this->authorize('Create Release');
        $this->release->create($request->all());

        return redirect()->back()->with('status', 'Release Added Successfully');

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $this->authorize('Show Release');

        $row = $this->release->show($id);

        // Eager load all relationships for the show page
        $row->load([
            'vendor', 'priority', 'targetSystem', 'responsibleRtm',
            'status', 'releaseStatusObj',
            'attachments', 'feedbacks.creator',
            'stakeholders.changeRequest', 'stakeholders.creator',
            'changeRequests', 'risks.category', 'risks.status', 'risks.changeRequest',
            'teamMembers.role', 'teamMembers.user',
            'crAttachments.changeRequest', 'crAttachments.creator',
        ]);

        $logs = $this->release->DisplayLogs($id);

        return view("$this->view.show", compact('row', 'logs'));
    }

    // old method
    public function show_release($id)
    {
        $this->authorize('Show Release');
        $row = $this->release->find($id);

        $releaseWorkflowTypeId = WorkFlowType::where('name', 'Release')->whereNotNull('parent_id')->value('id');

        // $workflow = NewWorkFlow::where('type_id', $releaseWorkflowTypeId)->first();
        $workflow = NewWorkFlow::where('from_status_id', $row->release_status)->where('type_id', $releaseWorkflowTypeId)->where('active', '1')->orderBy('id', 'desc')->get();
        // dd($workflow);
        $statuses = [];
        foreach ($workflow as $key => $value) {
            $statuses[] = $value->workflowstatus[0]->to_status;
        }
        $statuses = collect($statuses);
        // $current_status = collect([$workflow[0]->from_status]);
        $current_status = Status::find($row->release_status);
        $current_status = collect([$current_status]);

        // $release = releaseResource::collection($release);

        $logs = $this->release->DisplayLogs($id);

        return view("$this->view.release_show", compact('row', 'statuses', 'logs'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id, Request $request)
    {
        $this->authorize('Edit Release');

        $row = $this->release->find($id);
        $row->load([
            'vendor', 
            'priority', 
            'targetSystem',
            'responsibleRtm',
            'attachments', 
            'feedbacks.creator', 
            'releaseStatusObj',
            'stakeholders.changeRequest', 
            'risks.category', 
            'risks.status', 
            'risks.changeRequest',
            'changeRequests',
            'teamMembers.role',
            'teamMembers.user',
            'teamMembers.creator',
            'crAttachments.changeRequest',
            'crAttachments.creator'
        ]);

        // Get all release statuses for dropdown
        $releaseStatuses = \App\Models\ReleaseStatus::getOrdered();
        
        // Get current release status
        $currentReleaseStatus = $row->releaseStatusObj;

        // Get vendors and priorities
        $vendors = \App\Models\Vendor::where('active', '1')->get();
        $priorities = \App\Models\Priority::all();
        
        // Get users for Responsible RTM dropdown
        $users = \App\Models\User::where('active', '1')->orderBy('name')->get();
        
        // Risk lookup data
        $riskCategories = \App\Models\RiskCategory::active()->get();
        $riskStatuses = \App\Models\RiskStatus::active()->get();
        
        // Release Team Roles
        $releaseTeamRoles = ReleaseTeamRole::all();

        // Active tab from query string
        $activeTab = $request->query('tab', 'tab_release');

        // Release Logs
        $logs = $this->release->DisplayLogs($id);

        return view("$this->view.edit", compact(
            'row', 
            'releaseStatuses',
            'currentReleaseStatus',
            'vendors', 
            'priorities', 
            'users',
            'riskCategories', 
            'riskStatuses',
            'releaseTeamRoles',
            'activeTab',
            'logs'
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(ReleaseRequest $request, $id)
    {
        $this->authorize('Edit Release');

        $this->release->update($request->except(['_token', '_method', 'technical_attachment']), $id);

        // Handle Technical Attachment file upload
        if ($request->hasFile('technical_attachment')) {
            $file = $request->file('technical_attachment');
            $fileName = $file->getClientOriginalName();
            $filePath = $file->store('release_attachments/' . $id, 'public');

            $this->release->createAttachment([
                'release_id' => $id,
                'file_name'  => $fileName,
                'file_path'  => $filePath,
                'user_id'    => Auth::id(),
            ]);

            // Log
            ReleaseLogs::create([
                'release_id' => $id,
                'user_id'    => Auth::id(),
                'log_text'   => "Technical attachment '{$fileName}' uploaded by " . (Auth::user()->name ?? 'admin'),
            ]);
        }

        return redirect()
            ->to(route('releases.edit', $id) . '?tab=tab_release')
            ->with('status', 'Release Updated Successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function lisVendor()
    {
        // If we switched to simple text field, this might be redundant, but keeping for backward compat if table exists.
        // If user wants specific vendor list, we might need a model. 
        // For now, returning empty or fetching distinct names.
        $list_vendo = \App\Models\Release::select('vendor_name')->distinct()->get();

        return response()->json(['data' => $list_vendo], 200);
    }

    public function lisReleaseStatus()
    {
        $listReleaseStatus = $this->release->listStatus();

        return response()->json(['data' => $listReleaseStatus], 200);
    }

    public function uploadAttachment(Request $request)
    {
        $request->validate([
            'release_id' => 'required|exists:releases,id',
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('release_attachments', $fileName, 'public');

        $this->release->createAttachment([
            'release_id' => $request->release_id,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'user_id' => auth()->id(),
        ]);

        return response()->json(['success' => 'File uploaded successfully']);
    }

    public function downloadAttachment($id)
    {
        $attachment = $this->release->findAttachment($id);
        if (!$attachment) {
            abort(404);
        }
        $path = storage_path('app/public/' . $attachment->file_path);
        
        return response()->download($path, $attachment->file_name);
    }

    public function ReleaseLogs($id)
    {
        $this->authorize('Show Release Logs');
        $logs = $this->release->DisplayLogs($id);

        return view("$this->view.logs", compact('logs'));
    }

    /**
     * Store or update a stakeholder record for a CR in a release.
     */
    public function storeStakeholder(Request $request, $release)
    {
        $request->validate([
            'cr_id' => 'required|exists:change_request,id',
            'high_impact_stakeholder' => ['required', 'email', 'max:255', new \App\Rules\ActiveDirectoryEmail],
            'moderate_impact_stakeholder' => ['required', 'email', 'max:255', new \App\Rules\ActiveDirectoryEmail],
            'low_impact_stakeholder' => ['required', 'email', 'max:255', new \App\Rules\ActiveDirectoryEmail],
        ]);

        \App\Models\ReleaseStakeholder::updateOrCreate(
            [
                'release_id' => $release,
                'cr_id' => $request->cr_id,
            ],
            [
                'high_impact_stakeholder' => $request->high_impact_stakeholder,
                'moderate_impact_stakeholder' => $request->moderate_impact_stakeholder,
                'low_impact_stakeholder' => $request->low_impact_stakeholder,
                'created_by' => auth()->id(),
            ]
        );

        // Log
        $cr = \App\Models\Change_request::find($request->cr_id);
        $crNo = $cr->cr_no ?? $request->cr_id;
        ReleaseLogs::create([
            'release_id' => $release,
            'user_id'    => auth()->id(),
            'log_text'   => "Stakeholder updated for CR #{$crNo} by " . (auth()->user()->name ?? 'admin'),
        ]);

        return redirect()
            ->to(route('releases.edit', $release) . '?tab=tab_stakeholder')
            ->with('status', 'Stakeholder added successfully');
    }

    /**
     * Delete a stakeholder record.
     */
    public function destroyStakeholder($release, $stakeholder)
    {
        $stakeholderRecord = \App\Models\ReleaseStakeholder::where('id', $stakeholder)
            ->where('release_id', $release)
            ->firstOrFail();

        // Capture info before deletion for logging
        $cr = $stakeholderRecord->changeRequest;
        $crNo = $cr->cr_no ?? $stakeholderRecord->cr_id;

        $stakeholderRecord->delete();

        // Log
        ReleaseLogs::create([
            'release_id' => $release,
            'user_id'    => auth()->id(),
            'log_text'   => "Stakeholder record for CR #{$crNo} deleted by " . (auth()->user()->name ?? 'admin'),
        ]);

        return redirect()
            ->to(route('releases.edit', $release) . '?tab=tab_stakeholder')
            ->with('status', 'Stakeholder deleted successfully');
    }

    /**
     * Store a new risk for a release.
     */
    public function storeRisk(Request $request, $release)
    {
        $request->validate([
            'cr_id' => 'nullable|exists:change_request,id',
            'risk_description' => 'required|string',
            'risk_category_id' => 'required|exists:risk_categories,id',
            'impact_level' => 'required|integer|min:1|max:5',
            'probability' => 'required|integer|min:1|max:5',
            'owner' => ['required', 'email', 'max:255', new \App\Rules\ActiveDirectoryEmail],
            'risk_status_id' => 'required|exists:risk_statuses,id',
            'mitigation_plan' => 'nullable|string',
            'contingency_plan' => 'nullable|string',
            'date_identified' => 'nullable|date',
            'target_resolution_date' => 'nullable|date',
            'comment' => 'nullable|string',
        ]);

        \App\Models\ReleaseRisk::create([
            'release_id' => $release,
            'cr_id' => $request->cr_id,
            'risk_description' => $request->risk_description,
            'risk_category_id' => $request->risk_category_id,
            'impact_level' => $request->impact_level,
            'probability' => $request->probability,
            'owner' => $request->owner,
            'risk_status_id' => $request->risk_status_id,
            'mitigation_plan' => $request->mitigation_plan,
            'contingency_plan' => $request->contingency_plan,
            'date_identified' => $request->date_identified,
            'target_resolution_date' => $request->target_resolution_date,
            'comment' => $request->comment,
            'created_by' => auth()->id(),
        ]);

        // Log
        $riskDesc = \Illuminate\Support\Str::limit($request->risk_description, 50);
        ReleaseLogs::create([
            'release_id' => $release,
            'user_id'    => auth()->id(),
            'log_text'   => "Risk added: '{$riskDesc}' by " . (auth()->user()->name ?? 'admin'),
        ]);

        return redirect()
            ->to(route('releases.edit', $release) . '?tab=tab_risk')
            ->with('status', 'Risk added successfully');
    }

    /**
     * Update an existing risk record.
     */
    public function updateRisk(Request $request, $release, $risk)
    {
        $request->validate([
            'cr_id' => 'nullable|exists:change_request,id',
            'risk_description' => 'required|string',
            'risk_category_id' => 'required|exists:risk_categories,id',
            'impact_level' => 'required|integer|min:1|max:5',
            'probability' => 'required|integer|min:1|max:5',
            'owner' => ['required', 'email', 'max:255', new \App\Rules\ActiveDirectoryEmail],
            'risk_status_id' => 'required|exists:risk_statuses,id',
            'mitigation_plan' => 'nullable|string',
            'contingency_plan' => 'nullable|string',
            'date_identified' => 'nullable|date',
            'target_resolution_date' => 'nullable|date',
            'comment' => 'nullable|string',
        ]);

        $riskRecord = \App\Models\ReleaseRisk::where('id', $risk)
            ->where('release_id', $release)
            ->firstOrFail();

        // Capture old values for change tracking
        $fieldsToTrack = [
            'cr_id'                  => 'Related CR',
            'risk_description'       => 'Risk Description',
            'risk_category_id'       => 'Category',
            'impact_level'           => 'Impact Level',
            'probability'            => 'Probability',
            'owner'                  => 'Owner',
            'risk_status_id'         => 'Status',
            'mitigation_plan'        => 'Mitigation Plan',
            'contingency_plan'       => 'Contingency Plan',
            'date_identified'        => 'Date Identified',
            'target_resolution_date' => 'Target Resolution Date',
            'comment'                => 'Comment',
        ];

        $oldValues = $riskRecord->only(array_keys($fieldsToTrack));

        $newData = [
            'cr_id' => $request->cr_id,
            'risk_description' => $request->risk_description,
            'risk_category_id' => $request->risk_category_id,
            'impact_level' => $request->impact_level,
            'probability' => $request->probability,
            'owner' => $request->owner,
            'risk_status_id' => $request->risk_status_id,
            'mitigation_plan' => $request->mitigation_plan,
            'contingency_plan' => $request->contingency_plan,
            'date_identified' => $request->date_identified,
            'target_resolution_date' => $request->target_resolution_date,
            'comment' => $request->comment,
        ];

        $riskRecord->update($newData);

        // Build change log with field names
        $changes = [];
        foreach ($fieldsToTrack as $field => $label) {
            $oldVal = (string) ($oldValues[$field] ?? '');
            $newVal = (string) ($newData[$field] ?? '');
            if ($oldVal !== $newVal) {
                // Resolve readable names for foreign keys
                if ($field === 'risk_category_id') {
                    $oldVal = optional(\App\Models\RiskCategory::find($oldValues[$field]))->name ?? $oldVal;
                    $newVal = optional(\App\Models\RiskCategory::find($newData[$field]))->name ?? $newVal;
                } elseif ($field === 'risk_status_id') {
                    $oldVal = optional(\App\Models\RiskStatus::find($oldValues[$field]))->name ?? $oldVal;
                    $newVal = optional(\App\Models\RiskStatus::find($newData[$field]))->name ?? $newVal;
                } elseif ($field === 'cr_id') {
                    $oldVal = optional(\App\Models\Change_request::find($oldValues[$field]))->cr_no ?? $oldVal;
                    $newVal = optional(\App\Models\Change_request::find($newData[$field]))->cr_no ?? $newVal;
                }
                $oldVal = $oldVal ?: '(empty)';
                $newVal = $newVal ?: '(empty)';
                $changes[] = "{$label}: {$oldVal} → {$newVal}";
            }
        }

        // Log
        $riskId = $riskRecord->risk_id; // e.g. RSK-001
        $userName = auth()->user()->name ?? 'admin';
        if (!empty($changes)) {
            $changeText = implode(', ', $changes);
            $logText = "Risk {$riskId} updated by {$userName}: {$changeText}";
        } else {
            $logText = "Risk {$riskId} saved (no changes) by {$userName}";
        }

        ReleaseLogs::create([
            'release_id' => $release,
            'user_id'    => auth()->id(),
            'log_text'   => $logText,
        ]);

        return redirect()
            ->to(route('releases.edit', $release) . '?tab=tab_risk')
            ->with('status', 'Risk updated successfully');
    }

    /**
     * Delete a risk record.
     */
    public function destroyRisk($release, $risk)
    {
        $riskRecord = \App\Models\ReleaseRisk::where('id', $risk)
            ->where('release_id', $release)
            ->firstOrFail();
        
        $riskDesc = \Illuminate\Support\Str::limit($riskRecord->risk_description, 50);
        $riskRecord->delete();

        // Log
        ReleaseLogs::create([
            'release_id' => $release,
            'user_id'    => auth()->id(),
            'log_text'   => "Risk deleted: '{$riskDesc}' by " . (auth()->user()->name ?? 'admin'),
        ]);

        return redirect()
            ->to(route('releases.edit', $release) . '?tab=tab_risk')
            ->with('status', 'Risk deleted successfully');
    }

    /**
     * AJAX: Search for a CR by cr_no (only Vendor workflow_type_id = 5)
     */
    public function searchCr(Request $request, $releaseId)
    {
        $this->authorize('Edit Release');

        $request->validate([
            'cr_no' => 'required|integer',
        ]);

        $crNo = $request->input('cr_no');
        
        $cr = \App\Models\Change_request::where('cr_no', $crNo)
            ->with(['CurrentRequestStatuses.status', 'requester', 'workflowType'])
            ->first();

        if (!$cr) {
            return response()->json([
                'success' => false,
                'message' => "CR #{$crNo} not found."
            ], 404);
        }

        // Check if Vendor workflow (workflow_type_id = 5)
        if ($cr->workflow_type_id != 5) {
            return response()->json([
                'success' => false,
                'message' => "CR #{$crNo} is not a Vendor CR. Only Vendor CRs can be linked to releases."
            ], 422);
        }

        // Check if already linked to another release
        if ($cr->release_name && $cr->release_name != $releaseId) {
            $linkedRelease = \App\Models\Release::find($cr->release_name);
            return response()->json([
                'success' => false,
                'message' => "CR #{$crNo} is already linked to Release #{$linkedRelease->id} ({$linkedRelease->name})."
            ], 422);
        }

        // Check if already linked to this release
        if ($cr->release_name == $releaseId) {
            return response()->json([
                'success' => false,
                'message' => "CR #{$crNo} is already linked to this Release."
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $cr->id,
                'cr_no' => $cr->cr_no,
                'title' => $cr->title,
                'status' => optional(optional($cr->CurrentRequestStatuses)->status)->status_name ?? '-',
                'requester' => optional($cr->requester)->name ?? '-',
                'workflow_type' => optional($cr->workflowType)->name ?? '-',
            ]
        ]);
    }

    /**
     * AJAX: Attach a CR to the release
     */
    public function attachCr(Request $request, $releaseId)
    {
        $this->authorize('Edit Release');

        $request->validate([
            'cr_no' => 'required|integer',
        ]);

        $release = $this->release->find($releaseId);
        if (!$release) {
            return response()->json([
                'success' => false,
                'message' => 'Release not found.'
            ], 404);
        }

        $crNo = $request->input('cr_no');
        $cr = \App\Models\Change_request::where('cr_no', $crNo)->first();

        if (!$cr) {
            return response()->json([
                'success' => false,
                'message' => "CR #{$crNo} not found."
            ], 404);
        }

        // Check if Vendor workflow (workflow_type_id = 5)
        if ($cr->workflow_type_id != '5') {
            return response()->json([
                'success' => false,
                'message' => "CR #{$crNo} is not a Vendor CR. Only Vendor CRs can be linked to releases."
            ], 422);
        }

        // Check if already linked to another release
        if ($cr->release_name && $cr->release_name != $releaseId) {
            return response()->json([
                'success' => false,
                'message' => "CR #{$crNo} is already linked to another Release."
            ], 422);
        }

        // Validate: if the CR's status would push the release to UAT or beyond,
        // the release mandatory fields must be filled first
        $crStatusRecord = $cr->currentStatusRel;
        $crStatusName = ($crStatusRecord && $crStatusRecord->status)
            ? $crStatusRecord->status->status_name
            : null;

        if ($crStatusName) {
            $mapping = \App\Models\ReleaseStatusMapping::where('cr_status_name', $crStatusName)->first();
            if ($mapping && $mapping->releaseStatus) {
                $restrictedStatuses = ['UAT', 'Smoke Test', 'Go Live', 'Complete'];
                if (in_array($mapping->releaseStatus->name, $restrictedStatuses)) {
                    $mandatoryFields = [
                        'release_description' => 'Release Description',
                        'priority_id' => 'Priority',
                        'release_start_date' => 'Release Start Date',
                        'go_live_planned_date' => 'Go Live Planned Date',
                        'responsible_rtm_id' => 'Responsible RTM',
                    ];

                    $missingFields = [];
                    foreach ($mandatoryFields as $field => $label) {
                        if (empty($release->$field)) {
                            $missingFields[] = $label;
                        }
                    }

                    if (!empty($missingFields)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Please update the mandatory release fields first'
                        ], 422);
                    }
                }
            }
        }

        // Attach CR to release
        $cr->release_name = $releaseId;
        $cr->save();

        // Log
        ReleaseLogs::create([
            'release_id' => $releaseId,
            'user_id'    => Auth::id(),
            'log_text'   => "CR #{$crNo} linked to release by " . (Auth::user()->name ?? 'admin'),
        ]);

        // Recalculate release status
        (new \App\Services\ReleaseStatusService())->recalculateForRelease($releaseId);

        // Get updated release status
        $release->refresh();
        $release->load('releaseStatusObj');

        return response()->json([
            'success' => true,
            'message' => "CR #{$crNo} linked to release successfully.",
            'release_status' => optional($release->releaseStatusObj)->name ?? 'Unknown'
        ]);
    }

    /**
     * AJAX: Detach a CR from the release
     */
    public function detachCr(Request $request, $releaseId)
    {
        $this->authorize('Edit Release');

        $request->validate([
            'cr_id' => 'required|integer',
        ]);

        $release = $this->release->find($releaseId);
        if (!$release) {
            return response()->json([
                'success' => false,
                'message' => 'Release not found.'
            ], 404);
        }

        $crId = $request->input('cr_id');
        $cr = \App\Models\Change_request::find($crId);

        if (!$cr) {
            return response()->json([
                'success' => false,
                'message' => 'CR not found.'
            ], 404);
        }

        // Check if CR belongs to this release
        if ($cr->release_name != $releaseId) {
            return response()->json([
                'success' => false,
                'message' => 'CR is not linked to this release.'
            ], 422);
        }

        // Detach CR from release
        $crNo = $cr->cr_no ?? $cr->id;
        $cr->release_name = null;
        $cr->save();

        // Log
        ReleaseLogs::create([
            'release_id' => $releaseId,
            'user_id'    => Auth::id(),
            'log_text'   => "CR #{$crNo} removed from release by " . (Auth::user()->name ?? 'admin'),
        ]);

        // Recalculate release status
        (new \App\Services\ReleaseStatusService())->recalculateForRelease($releaseId);

        // Get updated release status
        $release->refresh();
        $release->load('releaseStatusObj');

        return response()->json([
            'success' => true,
            'message' => 'CR removed from release successfully.',
            'release_status' => optional($release->releaseStatusObj)->name ?? 'Unknown'
        ]);
    }

    // ========================================
    // Release Team Members (AJAX)
    // ========================================

    public function storeTeamMember(Request $request, $releaseId)
    {
        $request->validate([
            'role_id' => 'required|exists:release_team_roles,id',
            'user_id' => 'required|exists:users,id',
            'mobile'  => 'required|digits:11',
        ]);

        $release = $this->release->find($releaseId);
        if (!$release) {
            return response()->json(['success' => false, 'message' => 'Release not found.'], 404);
        }

        $member = ReleaseTeamMember::create([
            'release_id' => $releaseId,
            'role_id'    => $request->role_id,
            'user_id'    => $request->user_id,
            'mobile'     => $request->mobile,
            'created_by' => Auth::id(),
        ]);

        $member->load(['role', 'user', 'creator']);

        // Log
        $roleName = $member->role->name ?? '';
        $userName = $member->user->name ?? '';
        ReleaseLogs::create([
            'release_id' => $releaseId,
            'user_id'    => Auth::id(),
            'log_text'   => "Added team member: {$userName} as {$roleName}",
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Team member added successfully.',
            'member'  => [
                'id'         => $member->id,
                'role_name'  => $member->role->name ?? '-',
                'user_name'  => $member->user->name ?? '-',
                'mobile'     => $member->mobile,
                'created_by' => $member->creator->name ?? '-',
                'created_at' => $member->created_at->format('d M Y, H:i'),
            ]
        ]);
    }

    public function destroyTeamMember($releaseId, $memberId)
    {
        $member = ReleaseTeamMember::where('release_id', $releaseId)->where('id', $memberId)->first();
        if (!$member) {
            return response()->json(['success' => false, 'message' => 'Team member not found.'], 404);
        }

        $roleName = $member->role->name ?? '';
        $userName = $member->user->name ?? '';

        $member->delete();

        // Log
        ReleaseLogs::create([
            'release_id' => $releaseId,
            'user_id'    => Auth::id(),
            'log_text'   => "Removed team member: {$userName} ({$roleName})",
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Team member removed successfully.'
        ]);
    }

    // ========================================
    // Release CR Attachments (AJAX)
    // ========================================

    public function storeCrAttachment(Request $request, $releaseId)
    {
        $request->validate([
            'cr_id'       => 'required|exists:change_request,id',
            'description' => 'nullable|string|max:1000',
            'type'        => 'nullable|string|max:255',
            'attachment'  => 'required|file|max:10240',
        ]);

        $release = $this->release->find($releaseId);
        if (!$release) {
            return response()->json(['success' => false, 'message' => 'Release not found.'], 404);
        }

        // Verify CR is linked to this release
        $cr = \App\Models\Change_request::find($request->cr_id);
        if (!$cr || $cr->release_name != $releaseId) {
            return response()->json([
                'success' => false,
                'message' => 'This CR is not linked to this release.'
            ], 422);
        }

        // Store file
        $file = $request->file('attachment');
        $fileName = $file->getClientOriginalName();
        $filePath = $file->store('release_cr_attachments/' . $releaseId, 'public');

        $attachment = ReleaseCrAttachment::create([
            'release_id'  => $releaseId,
            'cr_id'       => $request->cr_id,
            'description' => $request->description,
            'type'        => $request->type,
            'file_name'   => $fileName,
            'file_path'   => $filePath,
            'created_by'  => Auth::id(),
        ]);

        $attachment->load(['changeRequest', 'creator']);

        // Log
        $crNo = $cr->cr_no ?? $cr->id;
        ReleaseLogs::create([
            'release_id' => $releaseId,
            'user_id'    => Auth::id(),
            'log_text'   => "Uploaded attachment '{$fileName}' for CR #{$crNo}",
        ]);

         return response()->json([
            'success'    => true,
            'message'    => 'Attachment uploaded successfully.',
            'attachment' => [
                'id'          => $attachment->id,
                'cr_no'       => $cr->cr_no ?? $cr->id,
                'cr_title'    => $cr->title ?? '-',
                'description' => $attachment->description ?? '-',
                'type'        => $attachment->type ?? '-',
                'file_name'   => $attachment->file_name,
                'file_url'    => route('releases.cr-attachments.download', $attachment->id),
                'updated_by'  => $attachment->creator->name ?? '-',
                'updated_at'  => $attachment->created_at->format('d M Y, H:i'),
            ]
        ]);
    }

    public function destroyCrAttachment($releaseId, $attachmentId)
    {
        $attachment = ReleaseCrAttachment::where('release_id', $releaseId)->where('id', $attachmentId)->first();
        if (!$attachment) {
            return response()->json(['success' => false, 'message' => 'Attachment not found.'], 404);
        }

        $fileName = $attachment->file_name;
        $crNo = optional($attachment->changeRequest)->cr_no ?? $attachment->cr_id;

        // Delete file from storage
        if (Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        // Log
        ReleaseLogs::create([
            'release_id' => $releaseId,
            'user_id'    => Auth::id(),
            'log_text'   => "Deleted attachment '{$fileName}' from CR #{$crNo}",
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attachment deleted successfully.'
        ]);
    }

    /**
     * Download a CR attachment file.
     */
    public function downloadCrAttachment($id)
    {
        $attachment = ReleaseCrAttachment::findOrFail($id);
        $path = storage_path('app/public/' . $attachment->file_path);

        if (!file_exists($path)) {
            abort(404, 'File not found.');
        }

        return response()->download($path, $attachment->file_name);
    }
}

