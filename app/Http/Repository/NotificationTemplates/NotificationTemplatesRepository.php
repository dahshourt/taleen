<?php

namespace App\Http\Repository\NotificationTemplates;

use App\Contracts\NotificationTemplates\NotificationTemplatesRepositoryInterface;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Validator;

class NotificationTemplatesRepository implements NotificationTemplatesRepositoryInterface
{
    public function getAll()
    {
        return NotificationTemplate::paginate(10);
    }

    public function create($request)
    {
        $validator = Validator::make($request, [
            'template_name' => 'required|unique:notification_templates,name',
            'template_subject' => 'required',
            'template_body' => 'required',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        
        // Convert comma-separated placeholders to array
        $placeholders = null;
        if (!empty($request['available_placeholder'])) {
            $placeholders = array_map('trim', explode(',', $request['available_placeholder']));
        }
        
        NotificationTemplate::create([
            'name' => $request['template_name'], 
            'subject' => $request['template_subject'], 
            'body' => $request['template_body'] ?? null,
            'available_placeholders' => $placeholders,
            'is_active' => isset($request['is_active']) ? 1 : 0,
        ]);

        return redirect()->back()->with('status', 'Template Added Successfully');
    }

    public function find($id)
    {
        return NotificationTemplate::find($id);
    }

    public function list()
    {
        return NotificationTemplate::all();
    }

    public function update($request, $id)
    {
        $validator = Validator::make($request, [
            'template_name' => 'required|unique:notification_templates,name,' . $id,
            'template_subject' => 'required',
            'template_body' => 'required',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Convert comma-separated placeholders to array
        $placeholders = null;
        if (!empty($request['available_placeholder'])) {
            $placeholders = array_map('trim', explode(',', $request['available_placeholder']));
        }

        NotificationTemplate::where('id', $id)->update([
            'name' => $request['template_name'], 
            'subject' => $request['template_subject'], 
            'body' => $request['template_body'] ?? null,
            'available_placeholders' => $placeholders,
            'is_active' => isset($request['is_active']) ? 1 : 0,
        ]);
    }

    public function delete($id)
    {
        return NotificationTemplate::find($id)->delete();
    }
}
