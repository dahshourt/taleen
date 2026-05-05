<?php

namespace App\Http\Repository\Prerequisites;

use App\Contracts\Prerequisites\PrerequisitesRepositoryInterface;
use App\Models\Prerequisite;
use App\Models\PrerequisiteAttachment;
use App\Models\Change_request;
use App\Models\Status;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PrerequisitesRepository implements PrerequisitesRepositoryInterface
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAll()
    {
        return Prerequisite::all();
    }

    /**
     * @param array $requestData
     * @return mixed
     */
    public function create($requestData)
    {
        return DB::transaction(function () use ($requestData) {
            $data = collect($requestData);

            $prerequisite = Prerequisite::create($data->except(['comments', 'attachments'])->all());

            if (!empty($requestData['comments'])) {
                $prerequisite->comments()->create([
                    'user_id' => Auth::id(),
                    'comment' => $requestData['comments'],
                ]);
            }

            if (isset($requestData['attachments'])) {
                $this->handleAttachments($requestData['attachments'], $prerequisite->id);
            }

            $this->logAction($prerequisite, 'Prerequisite was created');

            $changeRequest = Change_request::find($requestData['promo_id']);
          

            // Fire prerequisite created event for notifications
            event(new \App\Events\PrerequisiteCreated(
                $prerequisite,
                $requestData['group_id'],
                $requestData['status_id'],
                $changeRequest
            ));

            return $prerequisite;
        });
    }

    /**
     * @param int $id
     * @return int
     */
    public function delete($id)
    {
        return Prerequisite::destroy($id);
    }

    /**
     * @param array $request
     * @param Prerequisite $model
     * @return Prerequisite
     */
    public function update($request, $model)
    {
        return DB::transaction(function () use ($request, $model) {
            $data = collect($request);

            // Update status if changed
            if (!empty($request['status_id']) && $model->status_id != $request['status_id']) {
                $oldStatusId = $model->status_id;
                $status = Status::find($request['status_id']);
                $model->update(['status_id' => $request['status_id']]);
                $changeRequest = Change_request::find($request['promo_id']);

                if ($status) {
                    $this->logAction($model, "Prerequisite status changed to < {$status->status_name} >");
                    
                    // Fire prerequisite status updated event for notifications
                    event(new \App\Events\PrerequisiteStatusUpdated(
                        $model->fresh(),
                        $model->group_id,
                        $oldStatusId,
                        $request['status_id'],
                        $changeRequest
                    ));
                }
            }

            // Update main record fields (excluding special fields)
            $model->update($data->except(['comments', 'attachments', 'status_id'])->all());

            // Add comment if provided
            if (!empty($request['comments'])) {
                $model->comments()->create([
                    'user_id' => Auth::id(),
                    'comment' => $request['comments'],
                ]);
                $this->logAction($model, "Comment < {$request['comments']} > was added");
            }

            // Add new attachments if provided
            if (isset($request['attachments'])) {
                $this->handleAttachments($request['attachments'], $model->id);

                $fileName = $request['attachments'] instanceof \Illuminate\Http\UploadedFile
                    ? $request['attachments']->getClientOriginalName()
                    : 'File';

                $this->logAction($model, "Attachment < {$fileName} > was added");
            }

            return $model;
        });
    }

    /**
     * @param int $id
     * @return Prerequisite|null
     */
    public function find($id)
    {
        return Prerequisite::find($id);
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginateAll()
    {
        if (session()->has('default_group')) {
            $group = session('default_group');
        } else {
            $group = Auth::user()->default_group ?? null;
        }

        $userId = Auth::id();

        $openStatus = Status::where('status_name', 'Open')->value('id');
        $pendingStatus = Status::where('status_name', 'Pending')->value('id');
        $closedStatus = Status::where('status_name', 'Closed')->value('id');

        $query = Prerequisite::query();

        $query->where(function ($q) use ($group, $openStatus) {
            if ($group) {
                // $q->where('group_id', $group); // Uncomment if group filtering is needed in future
            }
            $q->where('status_id', $openStatus);
        })
            ->orWhere(function ($q) use ($userId, $pendingStatus, $closedStatus) {
                $q->where('created_by', $userId)
                    ->whereIn('status_id', [$pendingStatus, $closedStatus]);
            });

        return $query->paginate(10);
    }

    /**
     * @param \Illuminate\Http\UploadedFile $file
     * @param int $prerequisiteId
     * @return void
     */
    protected function handleAttachments($file, $prerequisiteId)
    {
        if ($file->isValid()) {
            $uploadPath = public_path('uploads/prerequisites');

            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }

            $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();

            if ($file->move($uploadPath, $filename)) {
                PrerequisiteAttachment::create([
                    'prerequisite_id' => $prerequisiteId,
                    'user_id' => Auth::id(),
                    'file' => $filename,
                ]);
            }
        }
    }

    /**
     * Helper to log actions on prerequisite
     * 
     * @param Prerequisite $prerequisite
     * @param string $text
     * @return void
     */
    private function logAction($prerequisite, $text)
    {
        $prerequisite->logs()->create([
            'user_id' => Auth::id(),
            'log_text' => $text,
        ]);
    }
}
