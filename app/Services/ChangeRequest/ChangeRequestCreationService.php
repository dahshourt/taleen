<?php

namespace App\Services\ChangeRequest;

use App\Events\ChangeRequestCreated;
use App\Http\Controllers\Mail\MailController;
use App\Http\Repository\ChangeRequest\ChangeRequestStatusRepository;
use App\Http\Repository\Logs\LogRepository;
use App\Http\Repository\NewWorkFlow\NewWorkflowRepository;
use App\Models\Change_request;
use App\Models\ChangeRequestCustomField;
use App\Models\CustomField;
use App\Traits\ChangeRequest\ChangeRequestConstants;
use Auth;
use Illuminate\Support\Arr;
use App\Http\Repository\KPIs\KPIRepository;

class ChangeRequestCreationService
{
    use ChangeRequestConstants;

    protected $logRepository;

    protected $changeRequestStatusRepository;

    public function __construct()
    {
        $this->logRepository = new LogRepository();
        $this->changeRequestStatusRepository = new ChangeRequestStatusRepository();
        // $this->workflowRepository = new NewWorkflowRepository();
    }

    public function create(array $data): array
    {

        $customFieldData = $data;
        $workflow = new NewWorkflowRepository();
        $defaultStatus = $workflow->getFirstCreationStatus($data['workflow_type_id'])->from_status_id;

        $preparedData = $this->prepareCreateData($data, $defaultStatus);
        $changeRequest = Change_request::create($preparedData);

        // Link KPI if selected
        if (isset($data['kpi']) && $data['kpi']) {
            $kpiRepo = new KPIRepository();
            $kpiRepo->attachKpiToChangeRequest($data['kpi'], $changeRequest->cr_no);
        }

        $statusData = $this->prepareStatusData($customFieldData, $defaultStatus);

        $this->handleCustomFields($changeRequest->id, $statusData);
        $this->changeRequestStatusRepository->createInitialStatus($changeRequest->id, $statusData);

        $this->logRepository->logCreate($changeRequest->id, $statusData, null, 'create');

        // $this->sendCreationEmails($changeRequest, $statusData);
        event(new ChangeRequestCreated($changeRequest, $statusData));

        return [
            'id' => $changeRequest->id,
            'cr_no' => $changeRequest->cr_no,
        ];
    }

    public function generateCrNumber($workflowTypeId): int
    {
        // Get KAM workflow ID dynamically from DB
        $kamWorkflow = \App\Models\WorkFlowType::where('name', 'KAM')->first();
        $isKamWorkflow = $kamWorkflow && $workflowTypeId == $kamWorkflow->id;

        // Get the last CR number for this workflow type
        $lastCr = Change_request::where('workflow_type_id', $workflowTypeId)
            ->orderBy('cr_no', 'desc')
            ->first();

        // If this is a KAM workflow, use the default starting number (6000)
        if ($isKamWorkflow) {
            $firstCrNo = config('change_request.default_values.first_cr_no.default', 6000);

            // Find the highest CR number in the system
            $highestCr = Change_request::max('cr_no');

            // If no CRs exist yet, return the first CR number
            if (!$highestCr) {
                return $firstCrNo;
            }

            // Return the next available number (max + 1)
            return max($highestCr + 1, $firstCrNo);
        }

        // For other workflows, use their specific starting numbers
        $firstCrNo = config(
            "change_request.default_values.first_cr_no.{$workflowTypeId}",
            config('change_request.default_values.first_cr_no.default', 6000)
        );

        if (!$lastCr || !$lastCr->cr_no) {
            return $firstCrNo;
        }

        return $lastCr->cr_no + 1;
    }
    protected function prepareCreateData(array $data, int $defaultStatus): array
    {
        $crNo = $this->generateCrNumber($data['workflow_type_id']);
        $data['requester_id'] = Auth::id();
        $data['requester_name'] = Auth::user()->user_name;
        $data['requester_email'] = Auth::user()->email;
        $data['cr_no'] = $crNo;

        return Arr::only($data, $this->getRequiredFields());
    }

    protected function prepareStatusData(array $data, int $defaultStatus): array
    {
        $data['requester_id'] = Auth::id();
        $data['requester_name'] = Auth::user()->user_name;
        $data['requester_email'] = Auth::user()->email;
        $data['old_status_id'] = $defaultStatus;
        $data['new_status_id'] = $defaultStatus;

        return Arr::except($data, []);
    }

    protected function handleCustomFields(int $crId, array $data): void
    {
        $excludedKeys = ['_token', 'business_attachments', 'technical_attachments'];

        foreach ($data as $key => $value) {
            // Skip excluded keys and empty values
            if (in_array($key, $excludedKeys) || empty($value)) {
                continue;
            }

            $customField = CustomField::findId($key);

            if ($customField) {
                // Convert arrays to JSON string before saving
                $fieldValue = is_array($value) ? json_encode($value) : $value;

                ChangeRequestCustomField::updateOrCreate(
                    [
                        'cr_id' => $crId,
                        'custom_field_id' => $customField->id,
                        'custom_field_name' => $key,
                    ],
                    [
                        'custom_field_value' => $fieldValue,
                        'user_id' => Auth::user()->id,
                    ]
                );
            }
        }
    }

    protected function sendCreationEmails($changeRequest, $statusData): void
    {
        if (!config('change_request.mail_notifications.creation')) {
            return;
        }

        $mailController = new MailController();

        // Send mail to requester
        $mailController->notifyRequesterCrCreated(
            $statusData['requester_email'],
            $changeRequest->id,
            $changeRequest->cr_no
        );

        // Send mail to division manager if exists
        if (isset($statusData['division_manager'])) {
            $mailController->notifyDivisionManager(
                $statusData['division_manager'],
                $statusData['requester_email'],
                $changeRequest->id,
                $statusData['title'],
                $statusData['description'],
                $statusData['requester_name'],
                $changeRequest->cr_no
            );
        }
    }
}
