<?php

namespace App\Services;

// use PhpEws\EwsClient;
use App\Models\Change_request;
use Exception;
use jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseFolderIdsType;
use jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseItemIdsType;
use jamesiarmes\PhpEws\Client;
use jamesiarmes\PhpEws\Enumeration\BodyTypeResponseType;
use jamesiarmes\PhpEws\Enumeration\DefaultShapeNamesType;
use jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType;
use jamesiarmes\PhpEws\Enumeration\ItemQueryTraversalType;
use jamesiarmes\PhpEws\Request\FindItemType;
use jamesiarmes\PhpEws\Request\GetItemType;
use jamesiarmes\PhpEws\Type\DistinguishedFolderIdType;
use jamesiarmes\PhpEws\Type\IndexedPageViewType;
use jamesiarmes\PhpEws\Type\ItemIdType;
use jamesiarmes\PhpEws\Type\ItemResponseShapeType;
use App\Services\StatusConfigService;
use Log;
use Throwable;

class EwsCabMailReader
{
    protected $client;

    public function __construct()
    {
        $host = config('services.ews_cab.host');
        $username = config('services.ews_cab.username');
        $password = config('services.ews_cab.password');

        $this->client = new Client($host, $username, $password, Client::VERSION_2016);
        // $this->client->setCurlOptions([CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false]);
        /*if (config('services.ews.ssl_verify') === false) {
            $this->client->setCurlOptions([CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false]);
        }*/
    }

    public function readInbox($limit)
    {

        // Find items in Inbox
        $findRequest = new FindItemType();

        $findRequest->ItemShape = new ItemResponseShapeType();
        $findRequest->ItemShape->BaseShape = DefaultShapeNamesType::ID_ONLY;

        $folderId = new DistinguishedFolderIdType();
        $folderId->Id = DistinguishedFolderIdNameType::INBOX;

        $findRequest->ParentFolderIds = new NonEmptyArrayOfBaseFolderIdsType();
        $findRequest->ParentFolderIds->DistinguishedFolderId[] = $folderId;

        $findRequest->Traversal = ItemQueryTraversalType::SHALLOW;

        $view = new IndexedPageViewType();
        $view->BasePoint = 'Beginning';
        $view->Offset = 0;
        $view->MaxEntriesReturned = $limit;
        $findRequest->IndexedPageItemView = $view;

        $findResponse = $this->client->FindItem($findRequest);
        $items = [];

        /*if (!isset($findResponse->ResponseMessages->FindItemResponseMessage)) {
            return []; //Return empty array if no messages
        }*/

        foreach ($findResponse->ResponseMessages->FindItemResponseMessage as $responseMessage) {
            if ($responseMessage->ResponseClass !== 'Success') {
                continue;
            }

            foreach ($responseMessage->RootFolder->Items->Message as $message) {
                $items[] = $message->ItemId;
            }
        }
        // dd($items);
        if (empty($items)) {
            return [];
        }
        // Step 2: Use GetItem to fetch full content
        $getRequest = new GetItemType();
        $getRequest->ItemIds = new NonEmptyArrayOfBaseItemIdsType();
        $getRequest->ItemIds->ItemId = $items;

        $getRequest->ItemShape = new ItemResponseShapeType();
        $getRequest->ItemShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;
        $getRequest->ItemShape->BodyType = BodyTypeResponseType::HTML;

        $getResponse = $this->client->GetItem($getRequest);

        $results = [];

        foreach ($getResponse->ResponseMessages->GetItemResponseMessage as $messageResponse) {
            if ($messageResponse->ResponseClass !== 'Success') {
                continue;
            }

            foreach ($messageResponse->Items->Message as $msg) {
                $results[] = [
                    'subject' => $msg->Subject ?? '(No Subject)',
                    'from' => $msg->From->Mailbox->EmailAddress ?? '(Unknown)',
                    'date' => $msg->DateTimeReceived ?? '',
                    'body' => $msg->Body ? $msg->Body->_ : '(No Body)',
                    'id' => $msg->ItemId,
                ];
            }
        }

        // $this->moveToArchive($items);
        return $results;

    }

    public function handleApprovals(int $limit = 20): void
    {
        $messages = $this->readInbox($limit);

        foreach ($messages as $message) {
            $subject = $message['subject'];

            // Determine the approval type based on subject pattern (most specific first)
            $approvalType = null;
            $crNo = null;

            if (preg_match('/CR\s*#(\d+)\s*-.*Awaiting Operation DM\s*&\s*Capacity Approval/i', $subject, $m)) {
                $approvalType = 'operation_dm';
                $crNo = (int) $m[1];
            } elseif (preg_match('/CR\s*#(\d+)\s*-.*Awaiting Eng\.\s*Walaa Amin Approval/i', $subject, $m)) {
                $approvalType = 'eng_walaa';
                $crNo = (int) $m[1];
            } elseif (preg_match('/CR\s*#(\d+)\s*-.*Awaiting Director Approval/i', $subject, $m)) {
                $approvalType = 'director';
                $crNo = (int) $m[1];
            } elseif (preg_match('/CR\s*#(\d+)\s*-.*Awaiting Your Approval\s*-.*CAB/i', $subject, $m)) {
                $approvalType = 'cab';
                $crNo = (int) $m[1];
            } elseif (preg_match('/SR\s*#(\d+)\s*-.*Awaiting SR Approval/i', $subject, $m)) {
                $approvalType = 'sr_approval';
                $crNo = (int) $m[1];
            }

            if (! $approvalType || ! $crNo) {
                Log::warning('EWS CAB Mail Reader: Subject does not match any known approval pattern, the mail will be moved to Archive. Subject: ' . $subject);
                $this->moveToArchive([$message['id']]);

                continue;
            }

            Log::info("EWS CAB Mail Reader: Detected approval type '{$approvalType}' for CR #{$crNo}");

            try {
                $crId = Change_request::where('cr_no', $crNo)->value('id');

                if (! $crId) {
                    Log::warning("EWS CAB Mail Reader: CR #{$crNo} not found in the database, the mail will be moved to Archive");
                    $this->moveToArchive([$message['id']]);

                    continue;
                }
            } catch (Exception $e) {
                Log::error("EWS CAB Mail Reader: Database error while looking up CR #{$crNo}: " . $e->getMessage());

                continue;
            }

            $bodyPlain = strip_tags($message['body']);
            $action = $this->determineAction($bodyPlain);

            if (! $action) {
                Log::warning("EWS CAB Mail Reader: There is no action found in the mail for CR #{$crNo} (type: {$approvalType}), the mail will be moved to Archive");
                $this->moveToArchive([$message['id']]);

                continue;
            }

            Log::info("EWS CAB Mail Reader: CR #{$crNo} (type: {$approvalType}) The Action is: {$action}");

            // Route to the appropriate handler based on approval type
            switch ($approvalType) {
                case 'cab':
                    $this->processCabAction($crId, $action, $message['from']);
                    break;
                case 'operation_dm':
                    $this->processOperationDmAction($crId, $action, $message['from']);
                    break;
                case 'eng_walaa':
                    $this->processEngWalaaAction($crId, $action, $message['from']);
                    break;
                case 'director':
                    $this->processDirectorAction($crId, $action, $message['from']);
                    break;
                case 'sr_approval':
                    $this->processSrApprovalAction($crId, $action, $message['from']);
                    break;
            }

            // Move the processed message to Archive folder
            $this->moveToArchive([$message['id']]);
        }
    }

    /*protected function determineAction(string $text): ?string
    {
        $text = strtolower($text);
        if (strpos($text, 'approved') !== false) {
            return 'approved';
        }
        if (strpos($text, 'rejected') !== false) {
            return 'rejected';
        }
        return null;
    }*/

    protected function determineAction(string $text): ?string
    {
        $text = strtolower($text);
        preg_match_all('/\b(approve|approved|reject|rejected)\b/i', $text, $matches, PREG_OFFSET_CAPTURE);

        if (empty($matches[0])) {
            return null;
        }

        /*$lastMatch = end($matches[0]);
        $word = $lastMatch[0]; */

        // i will use the first match instead of last match because we may enable the approval by reply.
        $firstMatch = $matches[0][0][0];
        if (in_array($firstMatch, ['approve', 'approved'])) {
            return 'approved';
        }

        if (in_array($firstMatch, ['reject', 'rejected'])) {
            return 'rejected';
        }

        return null;
    }

    /* ======================================================================
     |  CAB Approval Handler (existing logic, renamed from processCrAction)
     * ====================================================================== */

    protected function processCabAction(int $crId, string $action, string $fromEmail): void
    {
        $cr = \App\Models\Change_request::find($crId);
        if (! $cr) {
            Log::warning("EWS CAB Mail Reader: CR #{$crId} not found whilst processing CAB {$action} from {$fromEmail}");

            return;
        }

        // Check CAB status
        $currentStatus = \App\Models\Change_request_statuse::where('cr_id', $crId)
            ->where('active', '1')
            ->value('new_status_id');

        $pendingCabId = StatusConfigService::getStatusId('pending_cab');
        $pendingCabApprovalId = StatusConfigService::getStatusId('pending_cab_approval');

        if (!in_array($currentStatus, [$pendingCabId, $pendingCabApprovalId])) {
            Log::warning("EWS CAB Mail Reader: CR #{$crId} is not in pending cab or pending cab approval status whilst processing {$action} from {$fromEmail}");
            return;
        }

        if ($action === 'approved') {
            $workflow_type_id = $cr->getSetStatus()->where('workflow_type', '0')->pluck('id')->first();
        } elseif ($action === 'rejected') {
            $workflow_type_id = $cr->getSetStatus()->where('workflow_type', '1')->pluck('id')->first();
        } else {
            Log::warning("EWS CAB Mail Reader: Unsupported action {$action} for CR #{$crId}");
            return;
        }

        $repo = new \App\Http\Repository\ChangeRequest\ChangeRequestRepository();
        $user = \App\Models\User::where('email', $fromEmail)->first();
        $userId = $user ? $user->id : null;

        $updateRequest = new \Illuminate\Http\Request([
            'old_status_id' => $currentStatus,
            'new_status_id' => $workflow_type_id,
            'cab_cr_flag' => '1',
            'user_id' => $userId,
        ]);

        try {
            $repo->update($crId, $updateRequest);
            Log::info("EWS CAB Mail Reader: CR #{$crId} CAB {$action} successfully by {$fromEmail}");
        } catch (Throwable $e) {
            Log::error("EWS CAB Mail Reader: Failed to CAB {$action} CR #{$crId} → " . $e->getMessage());
        }
    }

    // Operation DM & Capacity Approval Handler

    protected function processOperationDmAction(int $crId, string $action, string $fromEmail): void
    {
        $cr = \App\Models\Change_request::find($crId);
        if (! $cr) {
            Log::warning("EWS CAB Mail Reader: CR #{$crId} not found whilst processing Operation DM {$action} from {$fromEmail}");

            return;
        }

        // Validate that the sender is either the application's operation DM or the capacity team
        $application = $cr->application;
        $capacityTeamEmail = config('constants.mails.capacity_team');
        $userId = null;
        $isAuthorized = false;

        // Check if sender is the operation DM
        if ($application && $application->operation_dm) {
            $operationManager = \App\Models\User::find($application->operation_dm);
            if ($operationManager && $operationManager->email && strtolower($fromEmail) === strtolower($operationManager->email)) {
                $userId = $operationManager->id;
                $isAuthorized = true;
            }
        }

        // Check if sender is the capacity team
        if (! $isAuthorized && $capacityTeamEmail && strtolower($fromEmail) === strtolower($capacityTeamEmail)) {
            $capacityUser = \App\Models\User::where('email', $capacityTeamEmail)->first();
            if ($capacityUser) {
                $userId = $capacityUser->id;
                $isAuthorized = true;
            }
        }

        if (! $isAuthorized) {
            Log::warning("EWS CAB Mail Reader: Unauthorized Operation DM/Capacity {$action} attempt for CR #{$crId} from {$fromEmail}");

            return;
        }

        $repo = new \App\Http\Repository\ChangeRequest\ChangeRequestRepository();

        try {
            if ($action === 'approved') {
                $result = $repo->approveDeployment($crId, $userId);
            } elseif ($action === 'rejected') {
                $result = $repo->rejectDeployment($crId, $userId);
            } else {
                Log::warning("EWS CAB Mail Reader: Unsupported action {$action} for Operation DM CR #{$crId}");

                return;
            }

            if ($result['success']) {
                Log::info("EWS CAB Mail Reader: CR #{$crId} Operation DM {$action} successfully by {$fromEmail}. Message: {$result['message']}");
            } else {
                Log::warning("EWS CAB Mail Reader: CR #{$crId} Operation DM {$action} by {$fromEmail} was not successful. Message: {$result['message']}");
            }
        } catch (Throwable $e) {
            Log::error("EWS CAB Mail Reader: Failed to Operation DM {$action} CR #{$crId} → " . $e->getMessage());
        }
    }

    // Eng. Walaa Amin Approval Handler

    protected function processEngWalaaAction(int $crId, string $action, string $fromEmail): void
    {
        $cr = \App\Models\Change_request::find($crId);
        if (! $cr) {
            Log::warning("EWS CAB Mail Reader: CR #{$crId} not found whilst processing Eng. Walaa {$action} from {$fromEmail}");

            return;
        }

        // Validate that the sender is Eng. Walaa Amin
        $engWalaaEmail = config('constants.eng_walaa.eng_walaa');
        if (strtolower($fromEmail) !== strtolower($engWalaaEmail)) {
            Log::warning("EWS CAB Mail Reader: Unauthorized Eng. Walaa {$action} attempt for CR #{$crId} from {$fromEmail}. Expected: {$engWalaaEmail}");

            return;
        }

        // Check current status is pending_operation_approvals
        $currentStatus = \App\Models\Change_request_statuse::where('cr_id', $crId)
            ->where('active', '1')
            ->value('new_status_id');

        $pendingOperationApprovalsId = StatusConfigService::getStatusId('pending_operation_approvals');

        if ($currentStatus !== $pendingOperationApprovalsId) {
            Log::warning("EWS CAB Mail Reader: CR #{$crId} is not in pending operation approvals status whilst processing Eng. Walaa {$action} from {$fromEmail}");

            return;
        }

        // Get the workflow transition based on action
        if ($action === 'approved') {
            $workflow_type_id = $cr->getSetStatus()->where('workflow_type', '0')->pluck('id')->first();
        } elseif ($action === 'rejected') {
            $workflow_type_id = $cr->getSetStatus()->where('workflow_type', '1')->pluck('id')->first();
        } else {
            Log::warning("EWS CAB Mail Reader: Unsupported action {$action} for Eng. Walaa CR #{$crId}");

            return;
        }

        $repo = new \App\Http\Repository\ChangeRequest\ChangeRequestRepository();
        $user = \App\Models\User::where('email', $fromEmail)->first();
        $userId = $user ? $user->id : null;

        $req = new \Illuminate\Http\Request([
            'old_status_id' => $currentStatus,
            'new_status_id' => $workflow_type_id,
            'assign_to' => null,
            'user_id' => $userId,
        ]);

        try {
            $repo->update($crId, $req);
            Log::info("EWS CAB Mail Reader: CR #{$crId} Eng. Walaa {$action} successfully by {$fromEmail}");
        } catch (Throwable $e) {
            Log::error("EWS CAB Mail Reader: Failed to Eng. Walaa {$action} CR #{$crId} → " . $e->getMessage());
        }
    }

    // Director Approval Handler

    protected function processDirectorAction(int $crId, string $action, string $fromEmail): void
    {
        $cr = \App\Models\Change_request::find($crId);
        if (! $cr) {
            Log::warning("EWS CAB Mail Reader: CR #{$crId} not found whilst processing Director {$action} from {$fromEmail}");

            return;
        }

        // Check current status is cr_directors_approvals
        $currentStatus = \App\Models\Change_request_statuse::where('cr_id', $crId)
            ->where('active', '1')
            ->value('new_status_id');

        $directorsApprovalsId = StatusConfigService::getStatusId('cr_directors_approvals');

        if ($currentStatus !== $directorsApprovalsId) {
            Log::warning("EWS CAB Mail Reader: CR #{$crId} is not in directors approvals status whilst processing Director {$action} from {$fromEmail}");

            return;
        }

        // Get the workflow transition based on action
        if ($action === 'approved') {
            $workflow_type_id = $cr->getSetStatus()->where('workflow_type', '0')->pluck('id')->first();
        } elseif ($action === 'rejected') {
            $workflow_type_id = $cr->getSetStatus()->where('workflow_type', '1')->pluck('id')->first();
        } else {
            Log::warning("EWS CAB Mail Reader: Unsupported action {$action} for Director CR #{$crId}");

            return;
        }

        $repo = new \App\Http\Repository\ChangeRequest\ChangeRequestRepository();
        $user = \App\Models\User::where('email', $fromEmail)->first();
        $userId = $user ? $user->id : null;

        $updateRequest = new \Illuminate\Http\Request([
            'old_status_id' => $currentStatus,
            'new_status_id' => $workflow_type_id,
            'user_id' => $userId,
        ]);

        try {
            $repo->update($crId, $updateRequest);
            Log::info("EWS CAB Mail Reader: CR #{$crId} Director {$action} successfully by {$fromEmail}");
        } catch (Throwable $e) {
            Log::error("EWS CAB Mail Reader: Failed to Director {$action} CR #{$crId} → " . $e->getMessage());
        }
    }

    /* ======================================================================
     |  SR Approval Handler
     * ====================================================================== */

    protected function processSrApprovalAction(int $crId, string $action, string $fromEmail): void
    {
        $cr = \App\Models\Change_request::find($crId);
        if (! $cr) {
            Log::warning("EWS CAB Mail Reader: SR #{$crId} not found whilst processing SR Approval {$action} from {$fromEmail}");

            return;
        }

        $user = \App\Models\User::where('email', $fromEmail)->first();
        if (! $user) {
            Log::warning("EWS CAB Mail Reader: User not found for email {$fromEmail} whilst processing SR Approval {$action} for SR #{$crId}");

            return;
        }

        $repo = new \App\Http\Repository\ChangeRequest\ChangeRequestRepository();

        try {
            if ($action === 'approved') {
                $result = $repo->approveSrBusinessApproval($crId, $user->id);
            } elseif ($action === 'rejected') {
                $result = $repo->rejectSrBusinessApproval($crId, $user->id);
            } else {
                Log::warning("EWS CAB Mail Reader: Unsupported action {$action} for SR Approval SR #{$crId}");

                return;
            }

            if ($result['success']) {
                Log::info("EWS CAB Mail Reader: SR #{$crId} SR Approval {$action} successfully by {$fromEmail}. Message: {$result['message']}");
            } else {
                Log::warning("EWS CAB Mail Reader: SR #{$crId} SR Approval {$action} by {$fromEmail} was not successful. Message: {$result['message']}");
            }
        } catch (Throwable $e) {
            Log::error("EWS CAB Mail Reader: Failed to SR Approval {$action} SR #{$crId} → " . $e->getMessage());
        }
    }

    protected function getArchiveFolder()
    {
        // Try to find Archive folder by searching from different folders
        $searchRoots = [
            \jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType::INBOX,
            // \jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType::DELETEDITEMS,
            \jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType::DRAFTS,
        ];

        foreach ($searchRoots as $rootType) {
            try {
                // Get the folder to find its parent
                $getFolder = new \jamesiarmes\PhpEws\Request\GetFolderType();
                $getRootFolder = new \jamesiarmes\PhpEws\Type\DistinguishedFolderIdType();
                $getRootFolder->Id = $rootType;

                $getFolder->FolderIds = new \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseFolderIdsType();
                $getFolder->FolderIds->DistinguishedFolderId[] = $getRootFolder;
                $getFolder->FolderShape = new \jamesiarmes\PhpEws\Type\FolderResponseShapeType();
                $getFolder->FolderShape->BaseShape = \jamesiarmes\PhpEws\Enumeration\DefaultShapeNamesType::ALL_PROPERTIES;

                $folderResponse = $this->client->GetFolder($getFolder);

                // Extract parent folder ID
                $parentFolderId = null;
                if (isset($folderResponse->ResponseMessages->GetFolderResponseMessage)) {
                    foreach ($folderResponse->ResponseMessages->GetFolderResponseMessage as $responseMessage) {
                        if ($responseMessage->ResponseClass === 'Success' && isset($responseMessage->Folders->Folder)) {
                            $folders = $responseMessage->Folders->Folder;
                            if (! is_array($folders)) {
                                $folders = [$folders];
                            }
                            foreach ($folders as $folder) {
                                if (isset($folder->ParentFolderId)) {
                                    $parentFolderId = $folder->ParentFolderId;
                                    break 2;
                                }
                            }
                        }
                    }
                }

                if (! $parentFolderId) {
                    continue; // Try next root type
                }

                // Now search for Archive in this parent folder
                $findFolder = new \jamesiarmes\PhpEws\Request\FindFolderType();
                $findFolder->Traversal = \jamesiarmes\PhpEws\Enumeration\FolderQueryTraversalType::SHALLOW;
                $findFolder->FolderShape = new \jamesiarmes\PhpEws\Type\FolderResponseShapeType();
                $findFolder->FolderShape->BaseShape = \jamesiarmes\PhpEws\Enumeration\DefaultShapeNamesType::ALL_PROPERTIES;

                $findFolder->ParentFolderIds = new \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseFolderIdsType();
                $findFolder->ParentFolderIds->FolderId[] = $parentFolderId;

                // Search for folder with DisplayName = "Archive"
                $isEqualTo = new \jamesiarmes\PhpEws\Type\IsEqualToType();
                $isEqualTo->FieldURI = new \jamesiarmes\PhpEws\Type\PathToUnindexedFieldType();
                $isEqualTo->FieldURI->FieldURI = 'folder:DisplayName';
                $isEqualTo->FieldURIOrConstant = new \jamesiarmes\PhpEws\Type\FieldURIOrConstantType();
                $isEqualTo->FieldURIOrConstant->Constant = new \jamesiarmes\PhpEws\Type\ConstantValueType();
                $isEqualTo->FieldURIOrConstant->Constant->Value = 'Archive';

                $findFolder->Restriction = new \jamesiarmes\PhpEws\Type\RestrictionType();
                $findFolder->Restriction->IsEqualTo = $isEqualTo;

                $response = $this->client->FindFolder($findFolder);

                // Check if we found the Archive folder
                if (isset($response->ResponseMessages->FindFolderResponseMessage)) {
                    foreach ($response->ResponseMessages->FindFolderResponseMessage as $responseMessage) {
                        if ($responseMessage->ResponseClass === 'Success' &&
                            isset($responseMessage->RootFolder->Folders->Folder)) {

                            $folders = $responseMessage->RootFolder->Folders->Folder;
                            if (! is_array($folders)) {
                                $folders = [$folders];
                            }

                            foreach ($folders as $folder) {
                                if (isset($folder->DisplayName) && $folder->DisplayName === 'Archive') {
                                    Log::info('EWS CAB Mail Reader: Archive folder found successfully using root: ' . $rootType);

                                    return $folder->FolderId;
                                }
                            }
                        }
                    }
                }

            } catch (Exception $e) {
                Log::debug("EWS CAB Mail Reader: Failed to search from root $rootType: " . $e->getMessage());

                continue; // Try next root
            }
        }
        throw new Exception('Archive folder not found in any of the searched locations');
    }

    protected function moveToArchive($items)
    {
        if (empty($items)) {
            return false;
        }

        try {
            $archiveFolderId = $this->getArchiveFolder();

            if (! $archiveFolderId) {
                throw new Exception('Failed to get or create Archive folder');
            }

            // Process items in batches to avoid timeouts
            $batchSize = 10;
            $chunks = array_chunk($items, $batchSize);
            $success = true;

            foreach ($chunks as $chunk) {
                $moveRequest = new \jamesiarmes\PhpEws\Request\MoveItemType();
                $moveRequest->ToFolderId = new \jamesiarmes\PhpEws\Type\TargetFolderIdType();
                $moveRequest->ToFolderId->FolderId = $archiveFolderId;

                $moveRequest->ItemIds = new \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseItemIdsType();

                // Convert each item to ItemIdType
                foreach ($chunk as $item) {
                    $itemId = new \jamesiarmes\PhpEws\Type\ItemIdType();
                    $itemId->Id = $item->Id;
                    $itemId->ChangeKey = $item->ChangeKey;
                    $moveRequest->ItemIds->ItemId[] = $itemId;
                }

                // Set to move (not copy)
                $moveRequest->ReturnNewItemIds = false;

                try {
                    $response = $this->client->MoveItem($moveRequest);

                    // Check for errors in the response
                    if (isset($response->ResponseMessages->MoveItemResponseMessage)) {
                        foreach ($response->ResponseMessages->MoveItemResponseMessage as $message) {
                            if ($message->ResponseClass !== 'Success') {
                                $errorMsg = $message->MessageText ?? 'Unknown error';
                                Log::error("Failed to move message to Archive: $errorMsg");
                                $success = false;
                            }
                        }
                    }
                } catch (Exception $e) {
                    Log::error('Error moving messages to Archive: ' . $e->getMessage());
                    $success = false;
                }
            }

            return $success;

        } catch (Exception $e) {
            Log::error('Error in moveToArchive: ' . $e->getMessage());

            return false;
        }
    }
}
