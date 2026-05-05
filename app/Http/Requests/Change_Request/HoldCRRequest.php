<?php

namespace App\Http\Requests\Change_Request;

use Illuminate\Foundation\Http\FormRequest;

class HoldCRRequest extends FormRequest
{
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
        'text/html', // ← to allow “web page saved as .doc”
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'change_request_id' => 'required|numeric|exists:change_request,cr_no',
            'resuming_date' => 'required|date|after:today',
            'hold_reason_id' => 'required|exists:hold_reasons,id',
            'justification' => 'nullable|string',
            'attachments' => ['nullable', 'array'],
            "attachments.*" => [
                'required',
                'file',
                'mimes:' . implode(',', self::ALLOWED_MIMES),
                'mimetypes:' . implode(',', self::ALLOWED_MIME_TYPES),
                'max:' . self::MAX_FILE_SIZE,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'change_request_id.required' => 'Please enter a CR Number.',
            'change_request_id.numeric' => 'CR Number must be a valid number.',
            'change_request_id.exists' => 'The selected CR Number does not exist in our records.',

            'resuming_date.required' => 'Please select a resuming date.',
            'resuming_date.date' => 'Resuming date must be a valid date.',
            'resuming_date.after' => 'Resuming date must be a future date (after today).',

            'hold_reason_id.required' => 'Please select a reason for putting the CR on hold.',
            'hold_reason_id.exists' => 'The selected hold reason is invalid.',

            'justification.string' => 'Justification must be valid text.',

            'attachments.array' => 'Attachments must be uploaded in the correct format.',
            
            'attachments.*.required' => 'Each attachment file is required.',
            'attachments.*.file' => 'Each attachment must be a valid file.',
            'attachments.*.mimes' => 'Attachment file type is not allowed. Allowed types: DOC, DOCX, XLS, XLSX, PDF, ZIP, RAR, JPEG, JPG, PNG, GIF, MSG.',
            'attachments.*.mimetypes' => 'Attachment file type is not supported. Please upload a valid file.',
            'attachments.*.max' => 'Each attachment must not exceed 50MB in size.',
        ];
    }

    public function attributes(): array
    {
        return [
            'change_request_id' => 'CR Number',
            'resuming_date' => 'Resuming Date',
            'hold_reason_id' => 'On-hold Reason',
            'justification' => 'On-Hold Justification',
            'attachments' => 'Attachments',
            'attachments.*' => 'Attachment',
        ];
    }
}
