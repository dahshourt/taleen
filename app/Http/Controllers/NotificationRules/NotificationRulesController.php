<?php

namespace App\Http\Controllers\NotificationRules;

use App\Factories\NotificationRules\NotificationRulesFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\NotificationRules\NotificationRulesRequest;
use App\Models\NotificationTemplate;
use App\Models\WorkFlowType;
use App\Models\Status;
use App\Models\User;
use App\Models\Group;

class NotificationRulesController extends Controller
{
    private $NotificationRules;

    public function __construct(NotificationRulesFactory $NotificationRules)
    {
        $this->NotificationRules = $NotificationRules::index();
        $this->view = 'NotificationRules';
        $view = 'NotificationRules';
        $route = 'notification_rules';
        $OtherRoute = 'notification_rules';
        
        $title = 'Notification Rules';
        $form_title = 'Notification Rule';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));
    }

    // Display a listing of the notification rules.
    public function index()
    {
        $this->authorize('List Notification Rules');
        $collection = $this->NotificationRules->list();

        return view("$this->view.index", compact('collection'));
    }

    // Show the form for creating a new notification rule.
    public function create()
    {
        $this->authorize('Create Notification Rules');
        
        $formData = $this->getFormData();
        
        return view("$this->view.create", $formData);
    }

    // Store a newly created notification rule in storage.
    public function store(NotificationRulesRequest $request)
    {
        $this->authorize('Create Notification Rules');
        
        $this->NotificationRules->create($request->validated());

        return redirect()->route('notification_rules.index')
            ->with('status', 'Notification Rule created successfully');
    }

    // Display the specified notification rule.
    public function show($id)
    {
        $this->authorize('Show Notification Rules');
        
        $row = $this->NotificationRules->getWithRecipients($id);
        
        if (!$row) {
            return redirect()->route('notification_rules.index')
                ->with('error', 'Notification Rule not found');
        }

        $formData = $this->getFormData();
        $formData['row'] = $row;
        
        // Parse conditions for display
        $formData['conditionDisplay'] = $this->formatConditionsForDisplay($row->conditions);
        
        return view("$this->view.show", $formData);
    }

    // Show the form for editing the specified notification rule.
    public function edit($id)
    {
        $this->authorize('Edit Notification Rules');
        
        $row = $this->NotificationRules->getWithRecipients($id);
        
        if (!$row) {
            return redirect()->route('notification_rules.index')
                ->with('error', 'Notification Rule not found');
        }

        $formData = $this->getFormData();
        $formData['row'] = $row;
        
        // Parse existing conditions for form
        if ($row->conditions && is_array($row->conditions)) {
            $formData['existingConditionType'] = array_key_first($row->conditions);
            $formData['existingConditionValue'] = $row->conditions[$formData['existingConditionType']] ?? null;
        }
        
        return view("$this->view.edit", $formData);
    }

    // Update the specified notification rule in storage.
    public function update(NotificationRulesRequest $request, $id)
    {
        $this->authorize('Edit Notification Rules');
        
        $this->NotificationRules->update($request->validated(), $id);

        return redirect()->route('notification_rules.index')
            ->with('status', 'Notification Rule updated successfully');
    }

    // Remove the specified notification rule from storage.
    public function destroy($id)
    {
        $this->authorize('Delete Notification Rules');
        
        $this->NotificationRules->delete($id);

        return redirect()->route('notification_rules.index')
            ->with('success', 'Notification Rule deleted successfully');
    }

    // Get form data for create/edit views.
    protected function getFormData()
    {
        return [
            'eventClasses' => [
                'App\Events\ChangeRequestCreated' => 'CR Created',
                'App\Events\ChangeRequestStatusUpdated' => 'CR Status Updated',
                'App\Events\MdsStartDateUpdated' => 'MDS Start Date Updated',
                'App\Events\DefectCreated' => 'Defect Created',
                'App\Events\DefectStatusUpdated' => 'Defect Status Updated',
                'App\Events\PrerequisiteCreated' => 'Prerequisite Created',
                'App\Events\PrerequisiteStatusUpdated' => 'Prerequisite Status Updated',
                'App\Events\ChangeRequestOnHold' => 'CR On Hold',
            ],
            'templates' => NotificationTemplate::where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'id'),
            'conditionTypes' => [
                'workflow_type' => 'Workflow Type Is',
                'workflow_type_not' => 'Workflow Type Is Not',
                'new_status_id' => 'New Status Is',
                'old_status_id' => 'Old Status Is',
                'custom_field' => 'Custom Field Equals',
            ],
            'customFields' => [
                'need_design' => 'Need Design',
                'application_id' => 'Application ID',
            ],
            'workflowTypes' => WorkFlowType::active()
                ->orderBy('name')
                ->pluck('name', 'id'),
            'statuses' => Status::active()
                ->orderBy('status_name')
                ->pluck('status_name', 'id'),
            'users' => User::where('active', '1')
                ->orderBy('user_name')
                ->pluck('user_name', 'id'),
            'groups' => Group::where('active', '1')
                ->orderBy('title')
                ->pluck('title', 'id'),
            'recipientTypes' => collect(config('notification_recipient_types', []))
                ->groupBy('category'),
            'channels' => [
                'to' => 'TO',
                'cc' => 'CC',
                'bcc' => 'BCC',
            ],
        ];
    }

    /**
     * Format conditions array for human-readable display.
     *
     * @param array|null $conditions
     * @return string
     */
    protected function formatConditionsForDisplay($conditions)
    {
        if (empty($conditions) || !is_array($conditions)) {
            return 'No conditions (always execute)';
        }

        $labels = [
            'workflow_type' => 'Workflow Type Is',
            'workflow_type_not' => 'Workflow Type Is Not',
            'new_status_id' => 'New Status Is',
            'old_status_id' => 'Old Status Is',
            'custom_field' => 'Custom Field',
        ];

        $parts = [];
        foreach ($conditions as $type => $value) {
            $label = $labels[$type] ?? $type;
            
            // Handle custom_field condition
            if ($type === 'custom_field' && is_array($value)) {
                $fieldName = $value['name'] ?? 'unknown';
                $fieldValue = $value['value'] ?? 'unknown';
                $parts[] = "Custom Field '{$fieldName}' = '{$fieldValue}'";
                continue;
            }
            
            // Try to get the actual name
            if (in_array($type, ['workflow_type', 'workflow_type_not'])) {
                $workflow = WorkFlowType::find($value);
                $valueName = $workflow ? $workflow->name : "ID: $value";
            } elseif (in_array($type, ['new_status_id', 'old_status_id'])) {
                $status = Status::find($value);
                $valueName = $status ? $status->status_name : "ID: $value";
            } else {
                $valueName = $value;
            }
            
            $parts[] = "$label: $valueName";
        }

        return implode(' AND ', $parts);
    }
}
