<?php

namespace App\Http\Controllers\ChangeRequest;

use App\Factories\Applications\ApplicationFactory;
use App\Factories\ChangeRequest\AttachmetsCRSFactory;
use App\Factories\ChangeRequest\ChangeRequestFactory;
use App\Factories\ChangeRequest\ChangeRequestStatusFactory;
use App\Factories\CustomField\CustomFieldGroupTypeFactory;
use App\Factories\Defect\DefectFactory;
use App\Factories\Logs\LogFactory;
use App\Factories\NewWorkFlow\NewWorkFlowFactory;
use App\Factories\Users\UserFactory;
use App\Factories\Workflow\Workflow_type_factory;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Mail\MailController;
use App\Http\Repository\ChangeRequest\ChangeRequestRepository;
use App\Http\Requests\Change_Request\Api\attachments_CRS_Request;
use App\Http\Requests\Change_Request\Api\changeRequest_Requests;
use App\Http\Resources\MyCRSResource;
use App\Models\ApplicationImpact;
use App\Models\Attachements_crs as attachements_crs;
use App\Models\Change_request;
use App\Models\Change_request_statuse;
use App\Models\ChangeRequestTechnicalTeam;
use App\Models\Group;
use App\Models\User;
use App\Models\WorkFlowType;
use Auth;
use DB;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Throwable;
use Validator;

class ChangeRequestController extends Controller
{
    private $changerequest;

    private $changerequeststatus;

    private $workflow;

    private $workflow_type;

    private $logs;

    private $users;

    private $applications;

    public function __construct(DefectFactory $defect, ChangeRequestFactory $changerequest, ChangeRequestStatusFactory $changerequeststatus, NewWorkFlowFactory $workflow, AttachmetsCRSFactory $attachments, Workflow_type_factory $workflow_type, CustomFieldGroupTypeFactory $custom_field_group_type, ApplicationFactory $applications)
    {
        $this->changerequest = $changerequest::index();
        $this->defects = $defect::index();
        $this->changerequeststatus = $changerequeststatus::index();
        $this->changerworkflowequeststatus = $workflow::index();
        $this->workflow_type = $workflow_type::index();
        $this->attachments = $attachments::index();
        $this->custom_field_group_type = $custom_field_group_type::index();
        $this->applications = $applications::index();

        $this->view = 'change_request';
        $view = 'change_request';
        $route = 'change_request';
        // $OtherRoute = 'user';
        $title = 'List Change Requests';
        $form_title = 'CR';
        view()->share(compact('view', 'route', 'title', 'form_title'));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $this->authorize('List change requests'); // permission check
            $collection = $this->changerequest->getAll();

            return view("$this->view.index", compact('collection'));

        } catch (AuthorizationException $e) {
            return redirect('/')->with('error', 'You do not have permission to access this page.');
        }

    }

    public function dvision_manager_cr()
    {
        try {
            $this->authorize('CR Waiting Approval'); // permission check

            $title = 'CR Waiting Approval';
            $collection = $this->changerequest->dvision_manager_cr();

            return view("$this->view.dvision_manager_cr", compact('collection', 'title'));
        } catch (AuthorizationException $e) {
            return redirect('/')->with('error', 'You do not have permission to access this page.');
        }
    }

    public function selectGroup($groupId)
    {

        // Assuming you have the user's group stored in a session
        session(['default_group' => $groupId]);
        $group = Group::find($groupId);

        if ($group) {
            session(['default_group_name' => $group->title]);
        } else {
            session()->forget('default_group_name'); // Clear the name if the group is not found
        }
        // Store user groups in session flash data
        session()->put('user_groups', $userGroups);

        return redirect()->back();
    }

    public function asd($group = null)
    {

        $gr = Group::find($group);

        if ($gr) {
            session(['default_group_name' => $gr->title]);
        } else {
            session()->forget('default_group_name'); // Clear the name if the group is not found
        }

        // Check if group is provided in the URL; if not, handle the absence
        if (! $group) {
            return redirect()->back()->with('error', 'No group provided.');
        }
        session(['default_group' => $group]);
        // Fetch all user groups for the dropdown
        $userGroups = auth()->user()->user_groups()->with('group')->get();

        // Check if the provided group exists in the user's groups
        $selectedGroup = $userGroups->pluck('group.id')->contains($group);

        if (! $selectedGroup) {
            // If the group does not exist in the user's groups, return back with an error message
            return redirect()->back()->with('error', 'You do not have access to this group.');
        }
        $selectedGroup = Group::find($group);
        session()->put('current_group', $group);
        session()->put('current_group_name', $selectedGroup->title);

        return redirect()->back();
        // session()->set('current_group',$group);
        // // Fetch the selected group object
        // $selectedGroup = Group::find($group);

        // // Fetch change request collection filtered by the group from the URL
        // $collection = $this->changerequest->getAll($group);

        // // Return the view with the selected group and user groups
        // return view("$this->view.index", compact('collection', 'selectedGroup', 'userGroups'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function Allsubtype()
    {
        $this->authorize('Create ChangeRequest');
        $get_workflow_subtype = $this->workflow_type->get_workflow_all_subtype_without_release();
        $target_systems = $this->applications->getAll();

        return view("$this->view.list_work_flow", compact('target_systems'));
    }

    public function create()
    {

        $this->authorize('Create ChangeRequest'); // permission check

        $form_type = 1; // create CR form type id
        $target_system_id = request()->target_system_id;
        $target_system = $this->applications->find($target_system_id);
        $workflow_type_id = $this->applications->workflowType($target_system_id)->id;

        $CustomFields = $this->custom_field_group_type->CustomFieldsByWorkFlowType($workflow_type_id, $form_type);
        $title = "Create $target_system->name CR";

        return view("$this->view.create", compact('CustomFields', 'workflow_type_id', 'target_system', 'title'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(changeRequest_Requests $request)
    {
        $this->authorize('Create ChangeRequest'); // permission check

        if ($request->hasFile('technical_attachments')) {

            $input_data = $request->all();

            $validator = Validator::make(
                $input_data, [
                    'technical_attachments.*' => 'required',
                    'file', 'mimes:docx,doc,xls,xlsx,pdf,zip,rar,jpeg,jpg,png,gif,msg',
                    'mimetypes: application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/pdf,application/zip,application/x-rar-compressed,image/jpeg,image/png,image/gif,application/vnd.ms-outlook', 'max:5120',
                ], [
                    'technical_attachments.*.required' => 'Please upload an attachment',
                    'technical_attachments.*.mimes' => 'Only docx,doc,xls,xlsx,pdf,zip,rar,jpeg,jpg,png,gif,msg are allowed',
                    'technical_attachments.*.mimetypes' => 'Only docx,doc,xls,xlsx,pdf,zip,rar,jpeg,jpg,png,gif,msg are allowed',
                    'technical_attachments.*.max' => 'Sorry! Maximum allowed size for an attachment is 2MB',
                ]
            );

            if ($validator->fails()) {
                // return redirect()->back()->withErrors('File not found.');
                // return redirect()->back()->with('error' , 'Created Successfully CR#'.$cr_id  );
                return redirect()->back()->withInput()->withErrors($validator);
            }
        }

        if ($request->hasFile('business_attachments')) {

            $input_data = $request->all();

            $validator = Validator::make(
                $input_data, [
                    'business_attachments.*' => 'required',
                    'file', 'mimes:docx,doc,xls,xlsx,pdf,zip,rar,jpeg,jpg,png,gif,msg',
                    'mimetypes: application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/pdf,application/zip,application/x-rar-compressed,image/jpeg,image/png,image/gif,application/vnd.ms-outlook', 'max:5120',
                ], [
                    'technical_attachments.*.required' => 'Please upload an attachment',
                    'technical_attachments.*.mimes' => 'Only docx,doc,xls,xlsx,pdf,zip,rar,jpeg,jpg,png,gif,msg are allowed',
                    'technical_attachments.*.mimetypes' => 'Only docx,doc,xls,xlsx,pdf,zip,rar,jpeg,jpg,png,gif,msg are allowed',
                    'technical_attachments.*.max' => 'Sorry! Maximum allowed size for an attachment is 2MB',
                ]
            );

            if ($validator->fails()) {
                // return redirect()->back()->withErrors('File not found.');
                // return redirect()->back()->with('error' , 'Created Successfully CR#'.$cr_id  );
                return redirect()->back()->withInput()->withErrors($validator);
            }
        }
        /* if(isset($request->parent_id))
        {
        $parentCR = DB::table('parents_crs')
            ->where('id', $request->parent_id)
            ->value('name');

          $res =  UserFactory::index()->get_parent_cr_user($parentCR);
            //$res[0]->id
            $request['developer_id'] = $res[0]->id;
        //dd($request);

        } */

        $cr_data = $this->changerequest->create($request->all());
        $cr_id = $cr_data['id'];
        $cr_no = $cr_data['cr_no'];
        /*if ($request->file()) {
            $this->attachments->add_files($request->file('attach'), $cr_id);
        }*/

        if ($request->hasFile('technical_attachments')) {
            $this->attachments->add_files($request->file('technical_attachments'), $cr_id, 1);

        }

        if ($request->hasFile('business_attachments')) {
            $this->attachments->add_files($request->file('business_attachments'), $cr_id, 2);

        }

        return redirect()->back()->with('status', 'Created Successfully CR#' . $cr_no);

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $this->authorize('Show ChangeRequest'); // permission check
        $cr = $this->changerequest->findById($id);
        if (! $cr) {
            return redirect()->back()->with('status', 'CR not exists');
        } // to check if the cr exists or not
        $cr = $this->changerequest->find($id);
        // dd($cr->logs);
        // $this->logs = LogFactory::index();
        if (! $cr) {
            $cr = change_request::find($id);
        }
        $form_type = 2; // create CR form type id
        $title = 'View Change Request';
        $workflow_type_id = $cr->workflow_type_id;
        $status_id = $cr->getCurrentStatus()?->status?->id;
        $status_name = $cr->getCurrentStatus()?->status?->name;
        $CustomFields = $this->custom_field_group_type->CustomFieldsByWorkFlowTypeAndStatus($workflow_type_id, $form_type, $status_id);
        // $logs_ers = $this->logs->get_by_cr_id($id);
        $logs_ers = $cr->logs;
        $technical_teams = Group::where('technical_team', '1')->get();

        return view("$this->view.show", compact('CustomFields', 'cr', 'status_name', 'title', 'logs_ers', 'technical_teams'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit_cab($id)
    {

        return $this->edit($id, true);
    }

    public function edit($id, $cab_cr_flag = false)
    {
        $this->authorize('Edit ChangeRequest'); // permission check
        // $this->logs = LogFactory::index();

        $user_email = auth()->user()->email;
        $user_email = strtolower($user_email);
        $division_manager = Change_request::where('id', $id)->value('division_manager');
        $division_manager = strtolower($division_manager);
        $current_status = Change_request_statuse::where('cr_id', $id)->where('active', '1')->value('new_status_id');

        // this condition to check if the division manager approve the CR or not
        if (request()->has('check_dm') && $user_email === $division_manager && $current_status != '22') {
            return response()->view("$this->view.action_response", [
                'isSuccess' => false,
                'title' => 'Error',
                'message' => 'You already took action on this CR',
                'status' => 400,
            ], 400);
            // return response('You already approved this CR.', 400);
        }

        if ($cab_cr_flag) {
            $cr = $this->changerequest->findCr($id);

            if (! $cr) {
                return redirect()->back()->with('status', 'You have no access to edit this CR');
            }
            if (empty($cr->cab_cr) or $cr->cab_cr->status == '2') {
                return redirect()->to('/')->with('status', 'CR already rejected');
            }
            $user_id = Auth::user()->id;
            $cr_cab_user = $cr->cab_cr->cab_cr_user->pluck('user_id')->toArray();

            if (! in_array($user_id, $cr_cab_user)) {
                return redirect()->to('/')->with('status', 'You have no access to edit this CR');
            }
            $check_if_approve = $cr->cab_cr->cab_cr_user->where('user_id', $user_id)->where('status', '1')->first();
            if ($check_if_approve) {
                return redirect()->to('/')->with('status', 'You alleardy approved before');
            }

        } else {

            $cr = $this->changerequest->findById($id);

            if (! $cr) {
                return redirect()->back()->with('status', 'CR not exists');
            } // to check if the cr exists or not

            $cr = $this->changerequest->find($id);
            // dd($cr);
            if (! $cr) {
                return redirect()->to('/change_request')->with('status', 'You have no access to edit this CR');
            } // to check if the user has access to edit this cr or not
        }

        // dd($cr->change_request_custom_fields);
        if ($cr->workflow_type_id == 13) {

            $parentCR = DB::table('parents_crs')
                ->where('id', $cr->change_request_custom_fields->where('custom_field_name', 'parent_id')->values()->toArray()[0]['custom_field_value'])
                ->value('application_name');
            $res = ApplicationFactory::index()->get_app_id_by_name($parentCR);
            if ($res) {
                $developer_users = UserFactory::index()->get_user_by_group($res->id);
            } else {
                $developer_users = UserFactory::index()->get_user_by_group($cr->application_id);
            }
        } else {
            $developer_users = UserFactory::index()->get_user_by_group($cr->application_id);
        }

        $sa_users = UserFactory::index()->get_user_by_department_id(6);
        $testing_users = UserFactory::index()->get_user_by_department_id(3);
        $work = $cr->workflow_type_id;

        $cap_users = UserFactory::index()->get_users_cap($cr->application_id);
        $rtm_members = UserFactory::index()->get_user_by_group_id(23);
        $technical_teams = Group::where('technical_team', '1')->get();
        $form_type = 2; // create CR form type id
        $workflow_type_id = $cr->workflow_type_id;
        // $logs_ers = $this->logs->get_by_cr_id($id);
        $logs_ers = $cr->logs;
        // $CustomFields = $this->custom_field_group_type->CustomFieldsByWorkFlowType($workflow_type_id, $form_type);
        $status_id = $cr->getCurrentStatus()->status->id;
        $status_name = $cr->getCurrentStatus()->status->name;
        $CustomFields = $this->custom_field_group_type->CustomFieldsByWorkFlowTypeAndStatus($workflow_type_id, $form_type, $status_id);

        $all_defects = $this->defects->all_defects($id);
        $technical_team_disabled = ChangeRequestTechnicalTeam::where('cr_id', $id)->get();
        $ApplicationImpact = ApplicationImpact::where('application_id', $cr->application_id)->select('impacts_id')->get();
        $man_day = $cr->change_request_custom_fields->where('custom_field_name', 'man_days')->values()->toArray();
        // dd($cr->technical_Cr_first->technical_cr_team->pluck('group')->pluck('title')->toArray());
        try {
            $selected_technical_teams = $cr->technical_Cr_first->technical_cr_team->pluck('group')->toArray();
        } catch (Throwable $e) {
            $selected_technical_teams = [];
        }
        // dd($selected_technical_teams);
        $reminder_promo_tech_teams = [];
        $reminder_promo_tech_teams = $cr->technical_Cr ? $cr->technical_Cr->technical_cr_team->where('status', '0')->pluck('group')->pluck('title')->toArray() : [];
        $reminder_promo_tech_teams_text = implode(',', $reminder_promo_tech_teams);

        $view_by_groups = $cr->getCurrentStatus()->status->group_statuses->where('type', '2')->pluck('group_id')->toArray();
        $assignment_users = UserFactory::index()->GetAssignmentUsersByViewGroups($view_by_groups);

        return view("$this->view.edit", compact('selected_technical_teams', 'man_day', 'technical_team_disabled', 'status_name', 'ApplicationImpact', 'cap_users', 'CustomFields', 'cr', 'workflow_type_id', 'logs_ers', 'developer_users', 'sa_users', 'testing_users', 'cab_cr_flag', 'technical_teams', 'all_defects', 'reminder_promo_tech_teams', 'reminder_promo_tech_teams_text', 'rtm_members', 'assignment_users'));

    }

    public function download($id)
    {
        $file = attachements_crs::findOrFail($id);
        $filePath = public_path('uploads/' . $file['file_name']); // in config
        // dd($filePath);
        if (file_exists($filePath)) {
            return response()->download($filePath, $file->file);
        }

        return redirect()->back()->withErrors('File not found.');
    } // end download

    public function deleteFile($id)
    {
        $file = attachements_crs::findOrFail($id);
        if (! auth()->user()->hasRole('Super Admin') && auth()->user()->id != $file->user->id) {
            return redirect()->back()->withErrors('You are not allowed to delete this file.');
        }
        $deleted = $this->attachments->delete_file($id);
        if ($deleted) {
            return redirect()->back()->with('success', 'File deleted successfully.');
        }

        return redirect()->back()->withErrors('File not found.');

    } // end delete

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(changeRequest_Requests $request, $id)
    {
        $this->authorize('Edit ChangeRequest'); // permission check
        if (isset($request->technical_teams) and ! empty($request->technical_teams)) {
            foreach ($request->technical_teams as $teamId) {

                DB::table('change_request_technical_team')->insert([
                    'cr_id' => $id,
                    'technical_team_id' => $teamId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $mails = [];
        // dd(empty($request->cap_users));
        if (! empty($request->cap_users)) {
            foreach ($request->cap_users as $users) {
                $mails[] = User::find($users)?->email;
            }
            // dd($mails);
            $mail = new MailController();
            $mail->send_mail_to_cap_users($mails, $id);
        }

        if ($request->hasFile('technical_attachments')) {

            $input_data = $request->all();

            $validator = Validator::make(
                $input_data, [
                    'technical_attachments.*' => 'required',
                    'file', 'mimes:docx,doc,xls,xlsx,pdf,zip,rar,jpeg,jpg,png,gif,msg',
                    'mimetypes: application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/pdf,application/zip,application/x-rar-compressed,image/jpeg,image/png,image/gif,application/vnd.ms-outlook', 'max:5120',
                ], [
                    'technical_attachments.*.required' => 'Please upload an attachment',
                    'technical_attachments.*.mimes' => 'Only docx,doc,xls,xlsx,pdf,zip,rar,jpeg,jpg,png,gif,msg are allowed',
                    'technical_attachments.*.mimetypes' => 'Only docx,doc,xls,xlsx,pdf,zip,rar,jpeg,jpg,png,gif,msg are allowed',
                    'technical_attachments.*.max' => 'Sorry! Maximum allowed size for an attachment is 2MB',
                ]
            );

            if ($validator->fails()) {
                // return redirect()->back()->withErrors('File not found.');
                // return redirect()->back()->with('error' , 'Created Successfully CR#'.$cr_id  );
                return redirect()->back()->withInput()->withErrors($validator);
            }
        }

        if ($request->hasFile('business_attachments')) {

            $input_data = $request->all();

            $validator = Validator::make(
                $input_data, [
                    'business_attachments.*' => 'required',
                    'file', 'mimes:docx,doc,xls,xlsx,pdf,zip,rar,jpeg,jpg,png,gif,msg',
                    'mimetypes: application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/pdf,application/zip,application/x-rar-compressed,image/jpeg,image/png,image/gif,application/vnd.ms-outlook', 'max:5120',
                ], [
                    'technical_attachments.*.required' => 'Please upload an attachment',
                    'technical_attachments.*.mimes' => 'Only docx,doc,xls,xlsx,pdf,zip,rar,jpeg,jpg,png,gif,msg are allowed',
                    'technical_attachments.*.mimetypes' => 'Only docx,doc,xls,xlsx,pdf,zip,rar,jpeg,jpg,png,gif,msg are allowed',
                    'technical_attachments.*.max' => 'Sorry! Maximum allowed size for an attachment is 2MB',
                ]
            );

            if ($validator->fails()) {
                // return redirect()->back()->withErrors('File not found.');
                // return redirect()->back()->with('error' , 'Created Successfully CR#'.$cr_id  );
                return redirect()->back()->withInput()->withErrors($validator);
            }
        }

        $cr_id = $this->changerequest->update($id, $request);

        if ($cr_id == false) {
            return redirect()->to('/change_request')->with('error', 'No group provided.');

        }

        if ($request->hasFile('technical_attachments')) {
            $this->attachments->add_files($request->file('technical_attachments'), $id, 1);

        }

        if ($request->hasFile('business_attachments')) {
            $this->attachments->add_files($request->file('business_attachments'), $id, 2);

        }

        return redirect()->to('/change_request')->with('status', 'Updated Successfully');
        // return redirect()->back()->with('status' , 'Updated Successfully' );
    }

    public function update_attach(attachments_CRS_Request $request)
    {
        $this->authorize('Edit ChangeRequest'); // permission check
        if ($request->file()) {
            $cr_id = $request->id;
            $this->attachments->add_files($request->file('filesdata'), $cr_id);
            // $this->attachments->update_files($request->file('filesdata'), $cr_id);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->authorize('Delete ChangeRequest'); // permission check

    }

    public function reorderhome()
    {
        $this->authorize('Shift ChangeRequest');

        return view("$this->view.shifiting");
    }

    public function reorderChangeRequest(Request $request)
    {
        $this->authorize('Shift ChangeRequest');
        // Validate the incoming request
        $request->validate([
            'change_request_id' => 'required|exists:change_request,id',
        ]);

        // Extract the Change Request ID from the request
        $crId = $request->input('change_request_id');

        // Call the repository method to reorder times
        $r = new ChangeRequestRepository();
        $result = $r->reorderTimes($crId);

        // Redirect back with success or error message
        if ($result['status']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    public function search_result($id)
    {
        $cr = '39390';

        return response()->json(['data' => $cr], 200);
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
        $title = 'My Assignments';

        return view("$this->view.index", compact('collection', 'title'));
    }

    public function my_crs()
    {
        $crs = $this->changerequest->my_crs();
        $my_crs = MyCRSResource::collection($crs);

        return response()->json(['data' => $my_crs], 200);
    }

    public function list_crs_by_user(Request $request)
    {

        $this->authorize('Show My CRs');
        $user_id = Auth::user();

        $user_name = $user_id->user_name;
        $status_promo_view = null;
        $workflow_type = $request->input('workflow_type', 'In House');
        if ($workflow_type == 'Promo') {
            $group_promo = Group::with('group_statuses')->find(50);
            $status_promo_view = $group_promo->group_statuses->where('type', \App\Models\GroupStatuses::VIEWBY)->pluck('status.id');

            $status_promo_view = $status_promo_view->toArray();

        }
        // dd($workflow_type);
        $query = new Change_request();
        $query = $query->with(['release', 'CurrentRequestStatuses'])->where('requester_id', $user_id->id);

        if ($workflow_type) {
            $workflow_type_id = WorkFlowType::where('name', $workflow_type)->whereNotNull('parent_id')->value('id');

            if ($workflow_type_id) {

                $query->where('workflow_type_id', $workflow_type_id);
            }
        }
        // dd($query->get()->toArray());
        $collection = $query->get();
        //  $collection = $collection->toArray();

        $r = new ChangeRequestRepository();
        $crs_in_queues = 0;

        // echo"<pre>";
        // print_r($crs_in_queues->toArray());
        // echo "</pre>"; die;
        return view("$this->view.CRsByuser", compact('collection', 'user_name', 'crs_in_queues', 'status_promo_view'));
    }

    /*public function Crsbyusers(Request $request)
    {
       $user_name = $request->userName;
       $user_id = User::where("user_name", $user_name)->pluck('id')->first();
       $collection = change_request::where("requester_id", $user_id)->get();
        return view("$this->view.CRsByuser",compact('collection', 'user_name'));

    }*/

    public function handleDivisionManagerAction(Request $request)
    {

        $cr_id = $request->query('crId');
        $action = $request->query('action');
        $token = $request->query('token');

        $cr = Change_request::find($cr_id);

        if (! $cr_id || ! $action || ! $token) {
            return response()->view("$this->view.action_response", [
                'isSuccess' => false,
                'title' => 'Error',
                'message' => 'Invalid request. Missing parameters.',
                'status' => 400,
            ], 400);
        }

        $cr = Change_request::find($cr_id);
        if (! $cr) {
            return response()->view("$this->view.action_response", [
                'isSuccess' => false,
                'title' => 'Error',
                'message' => 'Change Request not found.',
                'status' => 404,
            ], 404);
            // return response("Change Request not found.", 404);
        }

        $expectedToken = md5($cr->id . $cr->created_at . env('APP_KEY'));

        if ($token !== $expectedToken) {
            return response()->view("$this->view.action_response", [
                'isSuccess' => false,
                'title' => 'Error',
                'message' => 'Unauthorized access. Invalid token.',
                'status' => 403,
            ], 403);
            // return response("Unauthorized access. Invalid token.", 403);
        }

        $workflow_type_id = $cr->workflow_type_id;
        $current_status = Change_request_statuse::where('cr_id', $cr_id)->where('active', '1')->value('new_status_id');

        // this condition to check if the division manager take action on the CR
        if ($current_status != '22') {
            if ($current_status == '19') {
                return response()->view("$this->view.action_response", [
                    'isSuccess' => false,
                    'title' => 'Error',
                    'message' => 'You already rejected this CR.',
                    'status' => 400,
                ], 400);
                // return response('You already rejected this CR.', 400);
            }

            return response()->view("$this->view.action_response", [
                'isSuccess' => false,
                'title' => 'Error',
                'message' => 'You already approved this CR.',
                'status' => 400,
            ], 400);
            // return response('You already approved this CR.', 400);

        }

        if ($workflow_type_id == 3) {
            if ($action === 'approve') {
                $workflowIdForAction = 36;
            } else {
                $workflowIdForAction = 35;
            }
        }
        if ($workflow_type_id == 5) {
            if ($action === 'approve') {
                $workflowIdForAction = 188;
            } else {
                $workflowIdForAction = 184;
            }
        }
        $repo = new ChangeRequestRepository();

        $updateRequest = new \Illuminate\Http\Request([
            'old_status_id' => $current_status,
            'new_status_id' => $workflowIdForAction,
        ]);

        $repo->UpateChangeRequestStatus($cr_id, $updateRequest);

        if ($action === 'approve') {
            return response()->view("$this->view.action_response", [
                'isSuccess' => true,
                'title' => 'Success',
                'message' => 'CR #' . $cr_id . ' has been successfully approved.',
                'status' => 200,
            ], 200);
            // return response("CR #$cr_id has been successfully approved.");
        } elseif ($action === 'reject') {
            return response()->view("$this->view.action_response", [
                'isSuccess' => false,
                'title' => 'Error',
                'message' => 'CR #' . $cr_id . ' has been successfully rejected.',
                'status' => 200,
            ], 200);
            // return response("CR #$cr_id has been successfully rejected.");
        }

        return response()->view("$this->view.action_response", [
            'isSuccess' => false,
            'title' => 'Error',
            'message' => 'Invalid action specified.',
            'status' => 400,
        ], 400);
        // return response("Invalid action specified.", 400);

    }

    public function handleDivisionManagerAction1(Request $request)
    {

        $cr_id = $request->query('crId');
        $action = $request->query('action');
        $token = $request->query('token');

        if (! $cr_id || ! $action || ! $token) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Invalid request. Missing parameters.',
            ], 400);
        }

        $cr = Change_request::find($cr_id);
        // die($cr);
        if (! $cr) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Change Request not found.',
            ], 404);
        }

        $expectedToken = md5($cr->id . $cr->created_at . env('APP_KEY'));
        if ($token !== $expectedToken) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthorized access. Invalid token.',
            ], 403);
        }

        $workflow_type_id = $cr->workflow_type_id;
        $current_status = Change_request_statuse::where('cr_id', $cr_id)->where('active', '1')->value('new_status_id');

        if ($current_status != '22') {
            if ($current_status == '19') {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'You already rejected this CR.',
                ], 400);
            }

            return response()->json([
                'isSuccess' => false,
                'message' => 'You already approved this CR.',
            ], 400);

        }

        // Determine next status based on workflow_type_id
        if ($workflow_type_id == 3) {
            $workflowIdForAction = $action === 'approve' ? 36 : 35;
        } elseif ($workflow_type_id == 5) {
            $workflowIdForAction = $action === 'approve' ? 188 : 184;
        } else {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unsupported workflow type.',
            ], 400);
        }

        $repo = new ChangeRequestRepository();
        $updateRequest = new \Illuminate\Http\Request([
            'old_status_id' => $current_status,
            'new_status_id' => $workflowIdForAction,
        ]);
        $repo->UpateChangeRequestStatus($cr_id, $updateRequest);

        if ($action === 'approve') {
            return response()->json([
                'isSuccess' => true,
                'message' => 'CR #' . $cr_id . ' has been successfully approved.',
            ], 200);
        } elseif ($action === 'reject') {
            return response()->json([
                'isSuccess' => true,
                'message' => 'CR #' . $cr_id . ' has been successfully rejected.',
            ], 200);
        }

        return response()->json([
            'isSuccess' => false,
            'message' => 'Invalid action specified.',
        ], 400);

    }

    public function approved_active(Request $request)
    {

        $id = $request->id;
        $changerequest = $this->changerequest->UpateChangeRequestStatus($id, $request);

        // $this->changerequest->updateStatus($id);

        return response()->json([
            'message' => 'Updated Successfully',
            'status' => 'success',
        ]);

    }
}
