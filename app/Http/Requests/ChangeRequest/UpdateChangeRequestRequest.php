<?php

namespace App\Http\Requests\ChangeRequest;

use App\Http\Repository\ChangeRequest\ChangeRequestRepository;

class UpdateChangeRequestRequest extends BaseChangeRequestRequest
{
    public function rules(): array
    {
        $oldStatusId = $this->old_status_id ?? null;
        $dynamicRules = $this->getDynamicRules(2, $oldStatusId); // 2 = Edit
        $attachmentRules = $this->getAttachmentRules();

        return array_merge($dynamicRules, $attachmentRules);
    }

    public function messages(): array
    {
        $oldStatusId = $this->old_status_id ?? null;
        $dynamicMessages = $this->getDynamicMessages(2, $oldStatusId);
        $attachmentMessages = $this->getAttachmentMessages();

        return array_merge($dynamicMessages, $attachmentMessages);
    }

    public function attributes(): array
    {
        return [
            'title' => 'CR Subject',
        ];
    }

    protected function prepareForValidation()
    {
        $id = $this->route('change_request');
        
        // If route parameter is an object (implicit binding), get ID
        if (is_object($id)) {
            $id = $id->id;
        }

        $repo = new ChangeRequestRepository();
        $cr = $repo->find($id);
        
        if (!$cr) {
            $cr = $repo->findCr($id);
        }

        $this->merge([
            'active' => $this->has('active') ? '1' : '0',
            'testable' => $this->has('testable') ? '1' : '0',
            'need_ux_ui' => $this->has('need_ux_ui') ? 1 : 0,
            'cr' => $cr,
        ]);
    }
}
