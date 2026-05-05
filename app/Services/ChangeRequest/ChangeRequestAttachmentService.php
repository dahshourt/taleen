<?php

namespace App\Services\ChangeRequest;

use App\Factories\ChangeRequest\AttachmetsCRSFactory;
use App\Models\Attachements_crs;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChangeRequestAttachmentService
{
    private const ATTACHMENT_TYPE_TECHNICAL = 1;
    private const ATTACHMENT_TYPE_BUSINESS = 2;
    private const MAX_FILE_SIZE = 51200; // 50MB in KB

    private const ALLOWED_MIMES = [
        'doc', 'docx', 'xls', 'xlsx', 'pdf', 'zip', 'rar',
        'jpeg', 'jpg', 'png', 'gif', 'msg',
    ];

    private const ALLOWED_MIME_TYPES = [
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/pdf',
        'application/zip',
        'application/x-rar-compressed',
        'application/x-rar',
        'application/vnd.rar',
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/vnd.ms-outlook',
        'text/html',
    ];

    private $attachments;

    public function __construct(AttachmetsCRSFactory $attachments)
    {
        $this->attachments = $attachments::index();
    }

    public function validateAttachments(Request $request): void
    {
        $attachmentTypes = ['technical_attachments', 'business_attachments'];

        foreach ($attachmentTypes as $type) {
            if ($request->hasFile($type)) {
                $validator = Validator::make($request->all(), [
                    "{$type}.*" => [
                        'required',
                        'file',
                        'mimes:' . implode(',', self::ALLOWED_MIMES),
                        'mimetypes:' . implode(',', self::ALLOWED_MIME_TYPES),
                        'max:' . self::MAX_FILE_SIZE,
                    ],
                ], [
                    "{$type}.*.required" => 'Please upload an attachment',
                    "{$type}.*.mimes" => 'Only ' . implode(',', self::ALLOWED_MIMES) . ' files are allowed',
                    "{$type}.*.mimetypes" => 'Only ' . implode(',', self::ALLOWED_MIMES) . ' files are allowed',
                    "{$type}.*.max" => 'Maximum file size is 50MB',
                ]);

                if ($validator->fails()) {
                    throw new Exception($validator->errors()->first());
                }
            }
        }
    }

    public function handleFileUploads(Request $request, int $cr_id): void
    {
        if ($request->hasFile('technical_attachments')) {
            $this->attachments->add_files(
                $request->file('technical_attachments'),
                $cr_id,
                self::ATTACHMENT_TYPE_TECHNICAL
            );
        }

        if ($request->hasFile('business_attachments')) {
            $this->attachments->add_files(
                $request->file('business_attachments'),
                $cr_id,
                self::ATTACHMENT_TYPE_BUSINESS
            );
        }
    }

    public function download(int $id)
    {
        $file = Attachements_crs::findOrFail($id);
        $filePath = public_path("uploads/{$file->file_name}");

        if (!file_exists($filePath)) {
            throw new Exception('File not found.');
        }

        return response()->download($filePath, $file->file);
    }

    public function deleteFile(int $id)
    {
        $file = Attachements_crs::findOrFail($id);

        // Authorization check
        if (!auth()->user()->hasRole('Super Admin') && auth()->user()->id !== $file->user->id) {
            throw new Exception('You are not allowed to delete this file.');
        }

        $deleted = $this->attachments->delete_file($id);

        if ($deleted) {
            return true;
        }

        throw new Exception('File not found.');
    }

    public function addFiles($files, int $crId)
    {
        return $this->attachments->add_files($files, $crId);
    }
}
