<?php

namespace App\Http\Requests\ChangeRequest;

class StoreChangeRequestRequest extends BaseChangeRequestRequest
{
    public function rules(): array
    {
        $dynamicRules = $this->getDynamicRules(1); // 1 = Create
        $attachmentRules = $this->getAttachmentRules();

        return array_merge($dynamicRules, $attachmentRules);
    }

    public function messages(): array
    {
        $dynamicMessages = $this->getDynamicMessages(1);
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
        $this->merge([
            'active' => $this->has('active') ? '1' : '0',
            'testable' => $this->has('testable') ? '1' : '0',
            'need_ux_ui' => $this->has('need_ux_ui') ? 1 : 0,
        ]);
    }
}
