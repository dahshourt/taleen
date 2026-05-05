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

class EwsMailReader
{
    protected $client;

    public function __construct()
    {
        $host = config('services.ews.host');
        $username = config('services.ews.username');
        $password = config('services.ews.password');

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

            if (! preg_match('/CR\s*#(\d+)\s*-.*Awaiting Your Approval/i', $message['subject'], $m)) {
                Log::warning('EWS Mail Reader: There is no CR number found in the mail subject or the subject does not match the pattern, the mail will be moved to Archive');
                $this->moveToArchive([$message['id']]);

                continue;
            }

            $crNo = (int) $m[1];

            try {
                $crId = Change_request::where('cr_no', $crNo)->value('id');

                if (! $crId) {
                    Log::warning("EWS Mail Reader: CR #{$crNo} not found in the database, the mail will be moved to Archive");
                    $this->moveToArchive([$message['id']]);

                    continue;
                }
            } catch (Exception $e) {
                Log::error("EWS Mail Reader: Database error while looking up CR #{$crNo}: " . $e->getMessage());

                continue;
            }

            $bodyPlain = strip_tags($message['body']);
            $action = $this->determineAction($bodyPlain);

            if (! $action) {
                Log::warning("EWS Mail Reader: There is no action found in the mail for CR #{$crId}, the mail will be moved to Archive");
                $this->moveToArchive([$message['id']]);

                continue;
            }
            Log::warning("EWS Mail Reader: CR #{$crId} The Action is: {$action}");

            $this->processCrAction($crId, $action, $message['from']);

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


    // Execute repository logic to update CR status.

    protected function processCrAction(int $crId, string $action, string $fromEmail): void
    {
        $cr = \App\Models\Change_request::find($crId);
        if (! $cr) {
            Log::warning("EWS Mail Reader: CR #{$crId} not found whilst processing {$action} from {$fromEmail}");

            return;
        }

        // Ensure the sender is the assigned division manager
        if (strtolower($fromEmail) !== strtolower($cr->division_manager)) {
            Log::warning("EWS Mail Reader: Unauthorized {$action} attempt for CR #{$crId} from {$fromEmail}");
            return;
        }

        $currentStatus = \App\Models\Change_request_statuse::where('cr_id', $crId)
            ->where('active', '1')
            ->value('new_status_id');
        $businessApprovalId = StatusConfigService::getStatusId('business_approval');
        $divisionManagerApprovalId = StatusConfigService::getStatusId('division_manager_approval');

        if (!in_array($currentStatus, [$businessApprovalId, $divisionManagerApprovalId])) {
            Log::warning("EWS Mail Reader: CR #{$crId} is not in business approval or division manager approval status whilst processing {$action} from {$fromEmail}");
            return;
        }


        if ($action === 'approved') {
            $workflow_type_id = $cr->getSetStatus()->where('workflow_type', '0')->pluck('id')->first();
        } elseif ($action === 'rejected') {
            $workflow_type_id = $cr->getSetStatus()->where('workflow_type', '1')->pluck('id')->first();
        } else {
            Log::warning("EWS Mail Reader: Unsupported action {$action} for CR #{$crId}");
            return;
        }
        /*if ($cr->workflow_type_id == 3) {
            $newStatus = $action === 'approved' ? 36 : 35;
        } elseif ($cr->workflow_type_id == 5) {
            $newStatus = $action === 'approved' ? 188 : 184;
        } else {
            Log::warning("EWS Mail Reader: Unsupported workflow_type_id {$cr->workflow_type_id} for CR #{$crId}");

            return;
        }*/

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
            $repo->UpateChangeRequestStatus($crId, $req);
            Log::info("EWS Mail Reader: CR #{$crId} {$action} successfully by {$fromEmail}");
        } catch (Throwable $e) {
            Log::error("EWS Mail Reader: Failed to {$action} CR #{$crId} → " . $e->getMessage());
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
                                    Log::info('EWS Mail Reader: Archive folder found successfully using root: ' . $rootType);

                                    return $folder->FolderId;
                                }
                            }
                        }
                    }
                }

            } catch (Exception $e) {
                Log::debug("EWS Mail Reader: Failed to search from root $rootType: " . $e->getMessage());

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
