<?php

namespace App\Http\Controllers\KPIs;

use App\Exports\KpiChangeRequestsExport;
use App\Factories\KPIs\KPIFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\KPIs\KPIRequest;
use App\Models\Change_request;
use App\Models\Kpi;
use App\Services\KpiPillar\KpiPillarService;
use App\Services\KpiType\KpiTypeService;
use App\Services\Project\ProjectService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Validator;

class KPIController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    private $KPI;

    public function __construct(KPIFactory $KPI)
    {

        $this->KPI = $KPI::index();
        $this->view = 'kpis';
        $view = 'kpis';
        $route = 'kpis';
        $OtherRoute = 'kpis';

        $title = 'Strategic KPIs';
        $form_title = 'Strategic KPIs';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));

    }

    public function index()
    {
        $this->authorize('List KPIs');
        $collection = $this->KPI->getAll();

        return view("$this->view.index", compact('collection'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->authorize('Create KPIs');
        $priorities = Kpi::PRIORITY;
        $quarters = Kpi::QUARTER;
        $classifications = Kpi::CLASSIFICATION;
        $types = app(KpiTypeService::class)->getAllActive();
        $pillars = app(KpiPillarService::class)->getAllActive();
        $projects = app(ProjectService::class)->listUnlinked();

        return view("$this->view.create", compact('priorities', 'quarters', 'types', 'classifications', 'pillars', 'projects'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(KPIRequest $request)
    {
        $this->authorize('Create KPIs');
        $data = array_merge($request->validated(), [
            'kpi_comment' => $request->input('kpi_comment'),
        ]);

        $this->KPI->create($data);

        return redirect()->route('kpis.index')->with('status', 'KPI Added Successfully');

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $this->authorize('Show KPIs');
        $row = $this->KPI->find($id);

        $priorities = Kpi::PRIORITY;
        $quarters = Kpi::QUARTER;
        $types = app(KpiTypeService::class)->getAllActive();
        $pillars = app(KpiPillarService::class)->getAllActive();
        $classifications = Kpi::CLASSIFICATION;
        $projects = app(ProjectService::class)->listAll();
        $logs = $row ? $row->logs : collect();
        $comments = $row ? $row->comments : collect();
        $changeRequests = $row ? $row->changeRequests : collect();

        return view("$this->view.show", compact('row', 'priorities', 'quarters', 'types', 'classifications', 'logs', 'comments', 'changeRequests', 'pillars', 'projects'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->authorize('Edit KPIs');
        $row = $this->KPI->find($id);

        // If the KPI record is not found, return a 404 instead of breaking the view with a null->id error
        if (! $row) {
            abort(404);
        }

        $priorities = Kpi::PRIORITY;
        $quarters = Kpi::QUARTER;
        $types = app(KpiTypeService::class)->getAllActive();
        $pillars = app(KpiPillarService::class)->getAllActive();
        $classifications = Kpi::CLASSIFICATION;
        $projects = app(ProjectService::class)->listAll();
        $unlinkedProjects = app(ProjectService::class)->listUnlinked();
        $logs = $row->logs;
        $comments = $row->comments;
        $changeRequests = $row->changeRequests;

        return view("$this->view.edit", compact('row', 'priorities', 'quarters', 'types', 'classifications', 'logs', 'comments', 'changeRequests', 'pillars', 'projects', 'unlinkedProjects'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(KPIRequest $request, $id)
    {
        $this->authorize('Edit KPIs');
        $data = array_merge($request->validated(), [
            'kpi_comment' => $request->input('kpi_comment'),
        ]);
        // Classification is immutable on edit – ensure it cannot be changed
        unset($data['classification']);

        $this->KPI->update($data, $id);

        return redirect()->route('kpis.edit', $id)->with('status', 'KPI Updated Successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->authorize('Delete KPIs');
        $this->KPI->delete($id);

        return redirect()->back()->with('success', 'KPI deleted successfully!');
    }

    /**
     * Export KPIs to Excel.
     */
    public function export(): BinaryFileResponse
    {
        $this->authorize('Export KPIs');

        return Excel::download(new \App\Exports\KPIsExport(), 'kpis_' . date('Y-m-d_H-i-s') . '.xlsx');
    }

    /**
     * AJAX: search Change Request by cr_no for a specific KPI.
     */
    public function searchChangeRequest(Request $request, $kpiId)
    {
        $this->authorize('Edit KPIs');

        $request->validate([
            'cr_no' => ['required', 'integer'],
        ]);

        $crNo = $request->input('cr_no');

        $kpi = $this->KPI->find($kpiId);
        if (! $kpi) {
            return response()->json([
                'success' => false,
                'message' => 'KPI not found.',
            ], 404);
        }

        $cr = Change_request::with(['workflowType', 'currentStatusRel'])
            ->where('cr_no', $crNo)
            ->first();

        if (! $cr) {
            return response()->json([
                'success' => false,
                'message' => "Change Request #{$crNo} not found.",
            ], 404);
        }

        $alreadyLinked = $cr->kpis()->where('kpis.id', $kpi->id)->exists();

        $statusName = optional(optional($cr->currentStatusRel)->status)->status_name;
        $workflowName = $cr->workflowType->name ?? '';

        return response()->json([
            'success' => true,
            'already_linked' => $alreadyLinked,
            'data' => [
                'id' => $cr->id,
                'cr_no' => $cr->cr_no,
                'title' => $cr->title,
                'status' => $statusName,
                'workflow' => $workflowName,
                'show_url' => route('show.cr', $cr->id),
            ],
        ]);
    }

    /**
     * Export Change Requests linked to a specific KPI.
     */
    public function exportChangeRequests($kpiId): BinaryFileResponse
    {
        $this->authorize('Export KPIs');

        $kpi = $this->KPI->find($kpiId);
        if (! $kpi) {
            abort(404);
        }

        $fileName = 'kpi_' . $kpi->id . '_change_requests_' . date('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(new KpiChangeRequestsExport((int) $kpiId), $fileName);
    }

    /**
     * AJAX: attach a Change Request to KPI by cr_no.
     */
    public function attachChangeRequest(Request $request, $kpiId)
    {
        $this->authorize('Edit KPIs');

        $request->validate([
            'cr_no' => ['required', 'integer'],
        ]);

        $result = $this->KPI->attachChangeRequestByNumber($kpiId, $request->input('cr_no'));

        $statusCode = $result['success'] ?? false ? 200 : 422;

        return response()->json($result, $statusCode);
    }

    /**
     * AJAX: detach a Change Request from KPI.
     */
    public function detachChangeRequest($kpiId, $crId)
    {
        $this->authorize('Edit KPIs');

        $result = $this->KPI->detachChangeRequest($kpiId, $crId);

        $statusCode = $result['success'] ?? false ? 200 : 422;

        return response()->json($result, $statusCode);
    }

    /**
     * AJAX: Get initiatives by pillar ID
     */
    public function getInitiativesByPillar(Request $request)
    {
        $request->validate([
            'pillar_id' => ['required', 'exists:kpi_pillars,id'],
        ]);

        $pillarId = $request->input('pillar_id');

        $initiatives = \App\Models\KpiInitiative::where('pillar_id', $pillarId)
            ->where('status', '1')
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $initiatives,
        ]);
    }

    /**
     * AJAX: Get sub-initiatives by initiative ID
     */
    public function getSubInitiativesByInitiative(Request $request)
    {
        $request->validate([
            'initiative_id' => ['required', 'exists:kpi_initiatives,id'],
        ]);

        $initiativeId = $request->input('initiative_id');

        $subInitiatives = \App\Models\KpiSubInitiative::where('initiative_id', $initiativeId)
            ->where('status', '1')
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $subInitiatives,
        ]);
    }

    /**
     * AJAX: Check requester email in Active Directory
     */
    public function checkRequesterEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email:rfc,dns',
        ]);

        if ($validator->fails()) {
            return response()->json(['valid' => false, 'message' => 'Please enter a valid email address.']);
        }

        $mail = $request->email;

        // connection details
        $name = config('constants.active-directory.name');
        $pwd = config('constants.active-directory.pwd');
        $ldap_host = config('constants.active-directory.ldap_host');
        $ldap_binddn = config('constants.active-directory.ldap_binddn') . $name;
        $ldap_rootdn = config('constants.active-directory.ldap_rootdn');

        // Establish LDAP connection
        $ldap = ldap_connect($ldap_host);

        if ($ldap) {
            ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

            // Bind to LDAP server
            $ldapbind = ldap_bind($ldap, $ldap_binddn, $pwd);

            if ($ldapbind) {
                // Search for the email in Active Directory
                $escapedMail = ldap_escape($mail, '', LDAP_ESCAPE_FILTER);
                $search = "(mail=$escapedMail)";
                $result = ldap_search($ldap, $ldap_rootdn, $search);

                // If search returns results, the email exists
                if (ldap_count_entries($ldap, $result) > 0) {
                    return response()->json(['valid' => true, 'message' => 'Valid email address.']);
                }

                return response()->json(['valid' => false, 'message' => 'Email not found in Active Directory.']);
            }

            return response()->json(['valid' => false, 'message' => 'Unable to connect to Active Directory.']);
        }

        return response()->json(['valid' => false, 'message' => 'Unable to connect to LDAP server.']);
    }
}
